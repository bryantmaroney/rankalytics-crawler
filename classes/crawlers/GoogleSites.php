<?php

class GoogleSites extends CrawlerBase
{
    public $dbo, $type, $keywords, $proxy_count_file, $end_limit, $proxies, $max_results, $keywords_query, $proxy_query, $crawledDate;
    protected $defaultRowValue;

    function __construct($type, $config)
    {
        parent::__construct($config);

        $this->defaultRowValue = '-';

        // init db:
        $this->dbo = new DbHandle($config);

        // set type:
        $this->type = $type;

        // settings:
        $this->max_results = 30; // number of results from google
        $this->keywords_query = 'SELECT * FROM tbl_project_keywords';

        // settings based on type:
        switch ($this->type) {
            case 'rankalytics_crawler':
                $f_name = 'proxy_cs_sites';
                $for_crawler = 'small_craw';
                break;
            default:
                $f_name = 'proxy_cb_sites';
                $for_crawler = 'main_craw';
                break;
        }

        $this->proxy_count_file = $this->config['prj_path'] . '/stats/' . $f_name . '.txt';
        $offset = Helper::getCurrentProxyCount($this->proxy_count_file);

        // ..
        $r = $this->dbo->getProxies("SELECT * FROM proxy WHERE google_blocked='0' AND for_crawler='" . $for_crawler . "'");
        if ($offset >= count($r)) {
            $offset = 0;
            Helper::resetCurrentProxyCount($this->proxy_count_file);
        }

        $this->proxy_query = "SELECT * FROM proxy WHERE google_blocked='0' AND for_crawler='" . $for_crawler . "' LIMIT 999999 OFFSET " . $offset;

        // ->
        $this->startWorkflow();
    }

    function __destruct()
    {
        /* more or less needed, let's close the db connection: */
        $this->dbo->end_connection();
    }

    public function startWorkflow()
    {
        // get stuff from db:
        $this->keywords = $this->dbo->getKeywords($this->keywords_query);
        if (count($this->keywords) == 0) {
            exit();
        }
        $this->proxies = $this->dbo->getProxies($this->proxy_query);

        // curl static sets before work:
        SingleCurl::$dbo = $this->dbo;
        SingleCurl::$proxy_count = 0;
        SingleCurl::$proxies = $this->proxies;
        SingleCurl::$fileName = $this->proxy_count_file;

        // action time ->
        $this->getSites();

        //when we get here.. time for sleep / extra-sleep
        $sleep_time = rand(25, 59);

        /*if (Helper::getCurrentProxyCount($this->proxy_count_file) == 0) {
            $sleep_time = rand(25, 59);
        } else {
            $sleep_time = rand(3, 7);
        }*/

        echo 'Crawler is sleeping ' . $sleep_time . 's .. ' . "\n";
        sleep($sleep_time);

        // todo - remove it:
        echo('GoogleSites: finished' . "\n");
    }

    public function getSites()
    {
        $lang = $this->config['google_lang'];
        $g_tld = $this->config['google_tld'];

        foreach ($this->keywords as $k_no => $row) {
            // sets #1:
            $this->crawledDate = $row['crawled_date'];
            $keyword = trim($row['keyword']);
            $location = trim($row['location']);

            // sets #2:
            $search_string = urlencode($keyword);
            $search_string_2 = urlencode('[' . $keyword . ' ' . $location . ']');

            // sets #3:
            $initial = SingleCurl::$proxy_count;
            echo "Parsing: " . $search_string . " #with-" . $initial . " - " . SingleCurl::$proxies[SingleCurl::$proxy_count]['ip'] . " ";

            # search .COM
            $config['url'] = "https://www.google." . $g_tld . "/search?q=" . $search_string . "&hl=" . $lang . "&start=0&num=" . $this->max_results;
            $body = SingleCurl::action($config);
            $result_array_com = $this->processFields($body);

            # search .DE (=local)
            /*$lang = 'de';
            $config['url'] = "https://www.google.de/search?q=" . $search_string . "&hl=".$lang."&start=0&num=".$this->max_results;
            $body = SingleCurl::action($config);
            $result_array_de = $this->processFields($body);*/
            $result_array_de = array();

            # search LOCATION:
            $config['url'] = "https://www.google." . $g_tld . "/search?q=" . $search_string_2 . "&hl=" . $lang . "&start=0&num=" . $this->max_results;
            $body = SingleCurl::action($config);
            $result_array_local = $this->processFields($body);

            # search NEWS:
            $tbm = 'nws';
            $config['url'] = "https://www.google." . $g_tld . "/search?q=" . $search_string . "&tbm=" . $tbm . "&hl=" . $lang . "&start=0&num=" . $this->max_results;
            $body = SingleCurl::action($config);
            $news_array = $this->getNews($body);

            # search VIDEO:
            $tbm = 'vid';
            $config['url'] = "https://www.google." . $g_tld . "/search?q=" . $search_string . "&tbm=" . $tbm . "&hl=" . $lang . "&start=0&num=" . $this->max_results;
            $body = SingleCurl::action($config);
            $video_array = $this->getVideos($body);

            # search SHOP:
            $tbm = 'shop';
            $config['url'] = "https://www.google." . $g_tld . "/search?q=" . $search_string . "&tbm=" . $tbm . "&hl=" . $lang . "&start=0&num=" . $this->max_results;
            $body = SingleCurl::action($config);
            $shop_array = $this->getShop($body);

            echo "-> PARSED #with-" . SingleCurl::$proxy_count . "\n";

            /*echo '.com'."\n";
            print_r($result_array_com);
            echo '.de'."\n";
            print_r($result_array_de);
            echo '.local'."\n";
            print_r($result_array_local);
            exit;*/

            /*echo 'news '."\n";
            print_r($news_array);
            echo 'video '."\n";
            print_r($video_array);
            echo 'shop '."\n";
            print_r($shop_array);
            exit();*/

            /* BUILD UP QUERY, UPDATE, SAVE ! */
            echo ".. saving: ";
            $this->buildupAndUpdate($result_array_com, $result_array_de, $row, $news_array, $video_array, $shop_array, $result_array_local);
            echo "-> SAVED \n";

            //we need this in case it ever closes:
            $difference = SingleCurl::$proxy_count - $initial;
            $increment = 1;
            if ($difference > 0) {
                $increment += $difference;
            }

            Helper::incrementCurrentProxyCount($this->proxy_count_file, $increment);

            /* NEXT PROXY FOR NEW KEYWORD! */
            SingleCurl::$proxy_count++;
        }
    }

    //
    function buildupAndUpdate($result_array_com, $result_array_de, $row, $news_array, $video_array, $shop_array, $result_array_local)
    {
        if (count($result_array_com) > 0) {
            //insert:
            $final_array = array();
            foreach ($result_array_com as $key => $value) {
                $final_array[$key] = '(' . implode(',', $this->rowValues($row, $key, $value, $result_array_de, $result_array_local, $news_array, $video_array, $shop_array, $this->type)) . ')';
            }

            if (count($final_array) > 0) {
                $query = "INSERT INTO crawled_sites (" . implode(',', $this->tableFields()) . ") VALUES " . implode(',', $final_array);
                $this->dbo->runQuery($query);
            }

            //update `crawledDate` - table field is not added!
            $new_date = date('Y-m-d H:i:s');
            $query = "UPDATE \"tbl_project_keywords\" SET \"crawled_status\"='1', \"crawled_date\"='" . $new_date . "' WHERE \"unique_id\"='" . $row['unique_id'] . "'";
            $this->dbo->runQuery($query);
        }
    }

    // holds an array with fields that will be saved:
    function tableFields()
    {
        $fields = array(
            'unique_id',
            'site_url',
            'host',
            'crawler_name',
            'crawled_date',
            'keyword',
            'keyword_id',
            'rank',
            'title',
            'description',
            'google_com_rank',
            'title_com',
            'desc_com',
            'url_com',
            'rank_local',
            'title_local',
            'des_local',
            'url_local',
            'total_records',
            'total_records_com',
            'total_records_local',
            'news_title',
            'news_link',
            'news_desc',
            'news_total_result',
            'video_title',
            'video_link',
            'video_desc',
            'video_total_result',
            'shop_title',
            'shop_link',
            'shop_desc',
            'shop_price',
            'shop_image',
        );

        return $fields;
    }

    /**
     * @param $link
     *
     * @return bool
     */
    public function linkHasScheme($link)
    {
        return (strtolower(substr($link, 0, 4)) === 'http');
    }

    /**
     * @param $url
     * @return mixed
     */
    public function getHost($url)
    {
        $url = trim($url);

        // parse_url() won't work properly if 'http' is missing:
        if (!$this->linkHasScheme($url)) {
            $url = 'http://' . $url;
        }

        $parts = parse_url($url);
        if (isset($parts["host"])) {
            return str_ireplace("www.", "", $parts["host"]);
        }

        // this should never happen:
        return $this->defaultRowValue;
    }

    // returns an array with values corresponding to tableFields (IN SAME ORDER ! ) that will be saved:
    function rowValues($row, $key, $value, $result_array_de, $result_array_local, $news_array, $video_array, $shop_array, $crawler_name)
    {
        $default = $this->defaultRowValue;

        $values = array(
            md5(time() . "-" . rand(0, 10000)), //unique_id
            isset($result_array_de[$key]['site_url']) ? $result_array_de[$key]['site_url'] : $default,
            isset($value['site_url']) ? $this->getHost($value['site_url']): $default,
            $crawler_name,
            $this->crawledDate,
            $row['keyword'],
            $row['unique_id'],
            isset($result_array_de[$key]['rank']) ? $result_array_de[$key]['rank'] : $default,
            isset($result_array_de[$key]['title']) ? utf8_encode($result_array_de[$key]['title']) : $default,
            isset($result_array_de[$key]['desc']) ? utf8_encode($result_array_de[$key]['desc']) : $default,
            isset($value['rank']) ? $value['rank'] : $default,
            isset($value['title']) ? utf8_encode($value['title']) : $default,
            isset($value['desc']) ? utf8_encode($value['desc']) : $default,
            isset($value['site_url']) ? $value['site_url'] : $default,
            isset($result_array_local[$key]['rank']) ? $result_array_local[$key]['rank'] : $default,
            isset($result_array_local[$key]['title']) ? utf8_encode($result_array_local[$key]['title']) : $default,
            isset($result_array_local[$key]['desc']) ? utf8_encode($result_array_local[$key]['desc']) : $default,
            isset($result_array_local[$key]['site_url']) ? $result_array_local[$key]['site_url'] : $default,
            isset($result_array_de[$key]['total_records']) ? $result_array_de[$key]['total_records'] : $default,
            isset($value['total_records']) ? $value['total_records'] : $default,
            isset($result_array_local[$key]['total_records']) ? $result_array_local[$key]['total_records'] : $default,
            isset($news_array[$key]['title']) ? utf8_encode($news_array[$key]['title']) : $default,
            isset($news_array[$key]['link']) ? $news_array[$key]['link'] : $default,
            isset($news_array[$key]['desc']) ? utf8_encode($news_array[$key]['desc']) : $default,
            isset($news_array[$key]['total_result']) ? $news_array[$key]['total_result'] : $default,
            isset($video_array[$key]['title']) ? utf8_encode($video_array[$key]['title']) : $default,
            isset($video_array[$key]['link']) ? $video_array[$key]['link'] : $default,
            isset($video_array[$key]['desc']) ? utf8_encode($video_array[$key]['desc']) : $default,
            isset($video_array[$key]['total_result']) ? $video_array[$key]['total_result'] : $default,
            isset($shop_array[$key]['title']) ? utf8_encode($shop_array[$key]['title']) : $default,
            isset($shop_array[$key]['link']) ? $shop_array[$key]['link'] : $default,
            isset($shop_array[$key]['desc']) ? utf8_encode($shop_array[$key]['desc']) : $default,
            isset($shop_array[$key]['price']) ? $shop_array[$key]['price'] : $default,
            isset($shop_array[$key]['image']) ? $shop_array[$key]['image'] : $default,
        );

        // #1
        foreach ($values as $v_no => $val) {
            $values[$v_no] = '\'' . (pg_escape_string($values[$v_no])) . '\'';
        }

        // #2
        foreach ($values as $v_no => $val) {
            $values[$v_no] = utf8_decode($val);
        }

        return $values;
    }

    /* DOM FUNCTIONS */
    function getElementsByClassName(DOMDocument $DOMDocument, $ClassName, $tag)
    {
        $Elements = $DOMDocument->getElementsByTagName($tag);
        $Matched = "";

        foreach ($Elements as $node) {
            if (!$node->hasAttributes()) {
                continue;
            }

            $classAttribute = $node->attributes->getNamedItem('class');

            if (!$classAttribute) {
                continue;
            }

            $classes = explode(' ', $classAttribute->nodeValue);

            if (in_array($ClassName, $classes)) {
                $Matched = $node->nodeValue;
            }
        }

        return utf8_decode($Matched);
    }


    function getDomValue($DOMDocument, $ClassName, $tag)
    {
        $Elements = $DOMDocument->getElementsByTagName($tag);
        $Matched = "";

        foreach ($Elements as $node) {
            if (!$node->hasAttributes()) {
                continue;
            }

            $classAttribute = $node->attributes->getNamedItem('class');

            if (!$classAttribute) {
                continue;
            }

            $classes = explode(' ', $classAttribute->nodeValue);

            if (in_array($ClassName, $classes)) {
                $div_node = $node->getElementsByTagName("div");
                foreach ($div_node as $div_node_value) {
                    $Matched = $div_node_value->nodeValue;
                }
            }
        }

        return $Matched;
    }


    function getElementsByClassName2(DOMDocument $DOMDocument, $ClassName, $tag)
    {
        $Elements = $DOMDocument->getElementsByTagName($tag);
        $Matched = "";

        foreach ($Elements as $node) {
            if (!$node->hasAttributes()) {
                continue;
            }

            $classAttribute = $node->attributes->getNamedItem('class');

            if (!$classAttribute) {
                continue;
            }

            $classes = explode(' ', $classAttribute->nodeValue);

            if (in_array($ClassName, $classes)) {
                $Matched[] = $node;
            }
        }

        return $Matched;
    }

    function encodeData($string)
    {
        if (preg_match('!\S!u', $string)) {
            $encoded = htmlentities($string, ENT_COMPAT, 'UTF-8');
        } else {
            $encoded = htmlentities($string, ENT_COMPAT, 'ISO-8859-1');
        }

        $encoded = htmlentities(utf8_decode($encoded));
        return $encoded;
    }

    function formatVideo($url)
    {
        $url = str_replace("/url?q=", "", $url);
        $url = str_replace("%3F", "?", $url);
        $url = str_replace("%3D", "=", $url);
        $temp_array = explode("&sa=", $url);
        $url = $temp_array[0];
        return $url;
    }

    function formatUrl($url)
    {
        $url = str_replace("/url?q=", "", $url);
        $url = rtrim($url, ".");

        return $url;
    }

    function getContent(&$NodeContent = "", $nod)
    {
        if (is_object($nod)) {
            $NodList = $nod->childNodes;
            for ($j = 0; $j < $NodList->length; $j++) {
                $nod2 = $NodList->item($j);
                $nodemane = $nod2->nodeName;
                $nodevalue = $nod2->nodeValue;
                if ($nod2->nodeType == XML_TEXT_NODE) {
                    $NodeContent .= $nodevalue;
                } else {
                    $NodeContent .= "<$nodemane ";
                    $attAre = $nod2->attributes;
                    foreach ($attAre as $value) {
                        $NodeContent .= "{$value->nodeName}='{$value->nodeValue}'";
                    }
                    $NodeContent .= ">";
                    $this->getContent($NodeContent, $nod2);
                    $NodeContent .= "</$nodemane>";
                }
            }
        }
    }

    function dom2array_full($node)
    {
        $result = array();
        if ($node->nodeType == XML_TEXT_NODE) {
            $result = $node->nodeValue;
        } else {
            if ($node->hasAttributes()) {
                $attributes = $node->attributes;
                if ((!is_null($attributes)) && (count($attributes))) {
                    foreach ($attributes as $index => $attr) {
                        $result[$attr->name] = $attr->value;
                    }
                }
            }
            if ($node->hasChildNodes()) {
                $children = $node->childNodes;
                for ($i = 0; $i < $children->length; $i++) {
                    $child = $children->item($i);
                    if ($child->nodeName != '#text') {
                        if (!isset($result[$child->nodeName])) {
                            $result[$child->nodeName] = $this->dom2array($child);
                        } else {
                            $aux = $result[$child->nodeName];
                            $result[$child->nodeName] = array($aux);
                            $result[$child->nodeName][] = $this->dom2array($child);
                        }
                    }
                }
            }
        }
        return $result;
    }

    function dom2array($node)
    {
        $res = array();
        if ($node->nodeType == XML_TEXT_NODE) {
            $res = $node->nodeValue;
        } else {
            if ($node->hasAttributes()) {
                $attributes = $node->attributes;
                if (!is_null($attributes)) {
                    $res['@attributes'] = array();
                    foreach ($attributes as $index => $attr) {
                        $res['@attributes'][$attr->name] = $attr->value;
                    }
                }
            }
            if ($node->hasChildNodes()) {
                $children = $node->childNodes;
                for ($i = 0; $i < $children->length; $i++) {
                    $child = $children->item($i);
                    $res[$child->nodeName] = $this->dom2array($child);
                }
                $res['textContent'] = $node->textContent;
            }
        }
        return $res;
    }

    function isANumber($no)
    {
        $bad = array(',', '.');
        $no = str_replace($bad, '', $no);

        return is_numeric($no);
    }

    /* parsing functions: */
    function processFields($htmdata)
    {
        $result_array = array();
        if (!$htmdata) {
            echo "\tError browsing: ... [ ... ]";
            return $result_array;
        }

        $skip = 0;

        // now we test if (more) results are available
        if (strstr($htmdata, "/images/yellow_warning.gif")) {
            echo "No (more) results left";
            // WriteLog("No (more) results left<br/>");
            $skip = 1;
        }

        if (!$skip) {
            //$len=strlen((string)$htmdata);
            //WriteLog("Received $len bytes<br/>");
            // Now we parse the html content, putting it into a DOM tree
            $dom = new domDocument;
            $dom->strictErrorChecking = false;
            $dom->preserveWhiteSpace = true;

            // finding total number of results
            $found = false;
            if (preg_match('/id="resultStats">(.*?)<\/div>/', $htmdata, $matched)) {
                $matched = explode(' ', $matched[1]);
                foreach ($matched as $m_no => $m) {
                    if ($this->isANumber($m)) {
                        $found = $m;
                        break;
                    }
                }
            }

            if ($found === false) {
                $total_result = 0;
            } else {
                $total_result = $found;
            }

            // finding total number of results
            @$dom->loadHTML($htmdata);
            $lists = $dom->getElementsByTagName('li');
            $num = 0;
            $count = 0;
            foreach ($lists as $list) {
                unset($ar);
                unset($divs);
                unset($div);
                unset($cont);
                unset($result);
                unset($tmp);
                $ar = $this->dom2array_full($list);
                if (count($ar) < 2) {
                    //WriteLog("skipping advertisement and similar spam<br/>");
                    continue; // skipping advertisement and similar spam
                }
                if ((!isset($ar['class'])) || ($ar['class'] != 'g')) {
                    //WriteLog("skipping non-search results<br/>");
                    continue; // skipping non-search results
                }

                if (isset($ar['div'][1])) {
                    $ar['div'] =& $ar['div'][0];
                }
                if (isset($ar['div'][1])) {
                    $ar['div'] =& $ar['div'][0];
                }


                $divs = $list->getElementsByTagName('span');
                $div = $divs->item(1);

                $this->getContent($cont, $div);

                $num++;
                $result['title'] =& $ar['h3']['a']['textContent'];
                if (array_key_exists('@attributes', $ar['h3']['a'])) {
                    $tmp = strstr($ar['h3']['a']['@attributes']['href'], "http");

                    $string2 = explode('sa=U', $tmp); // formating url
                    $string2 = explode('&', $string2[0]);


                    $result['url'] = $string2[0];
                    if (trim($result['title']) == "" || trim($string2[0]) == "") // skipping wrong data
                    {
                        continue;
                    }
                    if (strstr($ar['h3']['a']['@attributes']['href'], "interstitial")) {
                        echo "!";
                    }

                    $tmp = parse_url($result['url']);


                    $result['host'] =& $tmp['host'];

                    if (strstr($cont, "<b >...</b><br >")) // remove some dirt behind the description
                    {
                        $result['desc'] = substr($cont, 0, strpos($cont, "<b >...</b><br >"));
                    } else {
                        if (strstr($cont, "<cite")) // remove some dirt behind the description in case the description was short
                        {
                            $result['desc'] = substr($cont, 0, strpos($cont, "<span class='f'><cite"));
                        } else {
                            $result['desc'] = $cont;
                        }
                    }

                    //Making id (Infine db wont support autoincrement and primarykeys)
                    $count++;
                    $result_array[] = array('site_url' => $string2[0], 'desc' => strip_tags($result['desc']), 'title' => strip_tags($result['title']), 'rank' => $count, 'total_records' => $total_result);
                }
            }
        }
        return $result_array;

    }

    function getVideos($data)
    {
        preg_match_all('/Ungefähr.*?Ergebnisse/i', $data, $matches, PREG_PATTERN_ORDER);

        if (isset($matches[0][0])) {
            $matches = explode(" ", $matches[0][0]);
            $total_result = str_replace(",", "", $matches[1]);
        } else {
            $total_result = '0';
        }

        $data = html_entity_decode(htmlentities($data), ENT_COMPAT, 'UTF-8');
        $result_array = array();

        preg_match_all("/<li class=\"g\s?(videobox)?\">(.*?)<\/li>/", $data, $matches);

        $dom = new domDocument;
        $dom->strictErrorChecking = false;
        $dom->preserveWhiteSpace = true;
        $title = "";
        $desc = "";
        $link = "";
        foreach ($matches[0] as $value) {

            @$dom->loadHTML($value);
            $desc = utf8_decode($this->getElementsByClassName($dom, "st", "span"));

            //@$dom->loadHTML($value);
            $lists = $dom->getElementsByTagName('a');
            foreach ($lists as $list) {
                $link = str_replace("/url?q=", "", $list->getAttribute("href"));
                break;
            }
            $lists = $dom->getElementsByTagName('h3');
            foreach ($lists as $list) {
                $title = utf8_decode($list->nodeValue);
                break;
            }
            $result_array[] = array("title" => $this->encodeData($title), "link" => $this->formatVideo($link), "desc" => $this->encodeData($desc), "total_result" => $total_result);
        }

        return $result_array;
    }

    function getNews($data)
    {
        preg_match_all('/Ungefähr.*?Ergebnisse/i', $data, $matches, PREG_PATTERN_ORDER);

        if (isset($matches[0][0])) {
            $matches = explode(" ", $matches[0][0]);
            $total_result = str_replace(",", "", $matches[1]);
        } else {
            $total_result = '0';
        }

        $data = html_entity_decode(htmlentities($data), ENT_COMPAT, 'UTF-8');

        $result_array = array();
        preg_match_all("/<li class=\"g\">(.*?)<\/li>/", $data, $matches);
        $dom = new domDocument;
        $dom->strictErrorChecking = false;
        $dom->preserveWhiteSpace = true;
        $title = "";
        $desc = "";
        $link = "";
        foreach ($matches[0] as $value) {

            @$dom->loadHTML($value);
            $desc = utf8_decode($this->getElementsByClassName($dom, "st", "div"));

            //@$dom->loadHTML($value);
            $lists = $dom->getElementsByTagName('a');
            foreach ($lists as $list) {
                $url = $list->getAttribute("href");
                if (strpos($url, 'adurl=') !== false) {
                    $url = explode("adurl=", $url);
                    $url = $url[1];
                }
                $link = $url;
                $temp_link = explode("&sa", $link);
                $link = $temp_link[0];
                break;
            }
            $lists = $dom->getElementsByTagName('h3');
            foreach ($lists as $list) {
                $title = utf8_decode($list->nodeValue);
                break;
            }
            $result_array[] = array("title" => $this->encodeData($title), "link" => $this->formatUrl($link), "desc" => $this->encodeData($desc), "total_result" => $total_result);
        }

        return $result_array;
    }

    function getShop($data)
    {
        $data = html_entity_decode(htmlentities($data), ENT_COMPAT, 'UTF-8');

        $dom = new domDocument;
        $dom->strictErrorChecking = false;
        $dom->preserveWhiteSpace = true;
        $link = "";
        $title = "";
        $desc = "";
        $price = "";
        $image = "";
        $result_array = array();

        @$dom->loadHTML($data);
        $dom = $this->getElementsByClassName2($dom, "g", "li");
        if ($dom) {
            foreach ($dom as $list) {
                $url = '-'; // <- fallback case;

                $list_link_dom = $list->getElementsByTagName('a');
                foreach ($list_link_dom as $list_link) {
                    $url = $list_link->getAttribute("href");
                    if (strpos($url, 'adurl=') !== false) {
                        $url = explode("adurl=", $url);
                        $url = $url[1];
                    }
                    $link = $url;
                    break;
                }

                $list_img = $list->getElementsByTagName('img');
                foreach ($list_img as $list_img_src) {
                    $image = $list_img_src->getAttribute("src");
                }

                $list_title_node = $list->getElementsByTagName('h3');
                foreach ($list_title_node as $list_title) {
                    $title = $list_title->nodeValue;
                }

                $price_node = $list->getElementsByTagName('b');
                foreach ($price_node as $price_node_value) {
                    $price = $price_node_value->nodeValue;

                    //a bit of formatting:
                    $price = $this->encodeData($price);
                    $price = str_ireplace(array('&amp;', ';'), '', $price);
                    $price = str_ireplace('nbsp', ':', $price);
                    break;
                }

                $desc = $this->getDomValue($list, "_Oj", "div"); // desc
                $desc = str_replace($title, "", $desc);

                $result_array[] = array("title" => $this->encodeData($title), "link" => $this->formatUrl($url), "desc" => $this->encodeData($desc), "price" => $price, "image" => $this->formatUrl($image));
            }
        }

        return $result_array;
    }
}