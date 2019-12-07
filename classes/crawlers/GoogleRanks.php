<?php

class GoogleRanks extends CrawlerBase
{
    public $dbo, $proxy_query, $proxies, $sites, $final_array;

    function __construct($type, $config)
    {
        parent::__construct($config);

        $this->dbo = new DbHandle($config);

        // defaults:
        $this->final_array = array();

        // queries:
        $this->proxy_query = "SELECT * FROM proxy WHERE google_blocked = 0 ORDER BY random()";
        $this->sites_query = 'SELECT unique_id,site_url,url_com,news_link,video_link,shop_link,url_local FROM crawled_sites WHERE page_rank IS NULL LIMIT 10';

        // get needed data:
        $this->proxies = $this->dbo->getProxies($this->proxy_query);
        $this->sites = $this->dbo->getResults($this->sites_query);

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
        if (count($this->sites) == 0) {
            echo('No un-crawled sites.' . "\n");
            return false;
        }

        $urls = array();
        foreach ($this->sites as $_no => $row) {
            $urls["page_rank-" . $row['unique_id']] = $row['site_url'];
            $urls["pg_rank_com-" . $row['unique_id']] = $row['url_com'];
            $urls["pg_rank_video-" . $row['unique_id']] = $row['video_link'];
            $urls["pg_rank_shop-" . $row['unique_id']] = $row['shop_link'];
            $urls["pg_rank_news-" . $row['unique_id']] = $row['news_link'];
            $urls["pg_rank_local-" . $row['unique_id']] = $row['url_local'];
        }

        $count = 0;
        // condition: as long as the final array has different no of elements than $urls array AND also we still have (enough proxies) proxy :
        echo "Parsing.. " . count($this->sites) . "\n";

        while ($this->countInnerFinalArray() != count($urls) AND count($this->proxies) > 0) {
            echo " Session #" . $count . " | proxies: #" . count($this->proxies) . " | status: " . $this->countInnerFinalArray() . " = " . count($urls) . "?\n";
            $count++;

            //do a session:
            $this->crawlingSessions($urls);

            //reset keys of proxy array
            $this->proxies = array_values($this->proxies);

            //sleep 1 second:
            sleep(1);
        }
        echo " Parsed!\n";

        echo "Ending info #" . $count . " | proxies: #" . count($this->proxies) . " | status: " . $this->countInnerFinalArray() . " = " . count($urls) . "?\n";

        //fallback case:
        if ($this->final_array != count($urls)) {
            foreach ($urls as $key => $url) {
                list($temp_name, $temp_key) = explode('-', $key);

                if (!isset($this->final_array[$temp_key][$temp_name])) {
                    $this->final_array[$temp_key][$temp_name] = '-';
                }
            }
        }

        if (count($this->final_array) > 0) {
            echo "Database operations: updating..\n";

            $all = array();
            foreach ($this->final_array as $key => $value) {
                $query = 'UPDATE crawled_sites SET ';
                $q = array();

                foreach ($this->getToUpdateFieldNames() as $f_key => $f_value) {
                    $q[] = $f_key . '=\'' . $value[$f_key] . '\'';
                }

                $query .= implode(', ', $q);
                $query .= ' WHERE unique_id=\'' . $key . '\'';
                $all[] = $query;
            }

            //run all:
            if (count($all) > 0) {
                $this->dbo->runQuery(implode(';', $all));
            }
        }

        sleep(rand(25, 59));
        echo('ranks finished.' . "\n");
    }

    // REMOVES USED PROXY BLOCKED OR NOT:
    public function removeUsedProxy($used_by_key, $response)
    {
        foreach ($this->proxies as $some_key => $proxy_info) {
            if (array_key_exists('used_by_key', $proxy_info) AND $proxy_info['used_by_key'] == $used_by_key) {
                #echo ' removing from array: '.$this->proxies[$some_key]['ip']." | returned: ".substr($response,0,10)." (..)\n";

                unset($this->proxies[$some_key]);
            }
        }
    }

    /* table field => associated array key (fields from crawled_sites table): */
    public function getToUpdateFieldNames()
    {
        return array_flip(array(
            'page_rank_de' => 'page_rank',
            'pg_rank_com' => 'pg_rank_com',
            'pg_rank_local' => 'pg_rank_local',
            'pg_rank_news' => 'pg_rank_news',
            'pg_rank_video' => 'pg_rank_video',
            'pg_rank_shop' => 'pg_rank_shop',
        ));
    }

    /* original functions: */
    public function request_callback($response, $info)
    {
        $split = explode("&key=", $info['url']);
        $temp_array = explode("-", $split[1]);
        $unique_id = $temp_array[1];
        $field = $temp_array[0];

        if (preg_match('/Rank_([\d]+):([\d]+):([\d]+)/', $response, $matched)) {
            $page_rank = $matched[3];

            //save page rank:
            $this->final_array[$unique_id][$field] = $page_rank;
        }

        if (strlen(trim($response)) == 0 OR stripos($response, 'href') !== false) {
            $this->final_array[$unique_id][$field] = '-'; // <- default value
        }

        //
        $this->removeUsedProxy($split[1], $response);
    }

    public function crawlingSessions($urls)
    {
        $g_tld = $this->config['google_tld'];

        $rc = new RollingCurl(array($this, 'request_callback'));
        $rc->window_size = 20;
        $count = 0;

        foreach ($urls as $key => $url) {
            list($temp_name, $temp_key) = explode('-', $key);

            //if the link doesn't have a ranked already:
            if (!isset($this->proxies[$temp_key][$temp_name])) {
                if (array_key_exists($count, $this->proxies)) {
                    //proxy for this request:
                    $proxy = $this->proxies[$count];
                    $this->proxies[$count]['used_by_key'] = $key; //<- we are saving the $key to proxy, so we could have a reference
                    //echo " ".$temp_name."-".$temp_key." using: ".$proxy['ip']."\n";

                    $query = "https://toolbarqueries.google." . $g_tld . "/tbr?client=navclient-auto&ch=" . $this->CheckHash($this->HashURL($url)) . "&features=Rank&q=info:" . $url . "&key=" . $key . "";
                    $request = new RollingCurlRequest($query, "GET", null, null, array(
                        CURLOPT_PROXY => $proxy['ip'],
                        CURLOPT_PROXYUSERPWD => $proxy['username'] . ":" . $proxy['password'],
                        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.16) Gecko/20080702 Firefox/2.0.0.16",
                        CURLOPT_HTTPPROXYTUNNEL => true
                    ));

                    //add curl:
                    $rc->add($request);

                    //increment proxy:
                    $count++;
                }
            }
        }

        // run curls ->
        if ($count > 0) {
            $rc->execute();
        }
    }

    /*
     * count the results inside the $this->final_array
     * ^ this is important for looping
     * */
    function countInnerFinalArray()
    {
        $total = 0;
        foreach ($this->final_array as $k => $inner) {
            $total += count($inner);
        }

        return $total;
    }

    /* GOOGLE PAGE RANK NEEDED FUNCTIONS: */
    function HashURL($String)
    {
        $Check1 = $this->StrToNum($String, 0x1505, 0x21);
        $Check2 = $this->StrToNum($String, 0, 0x1003F);

        $Check1 >>= 2;
        $Check1 = (($Check1 >> 4) & 0x3FFFFC0) | ($Check1 & 0x3F);
        $Check1 = (($Check1 >> 4) & 0x3FFC00) | ($Check1 & 0x3FF);
        $Check1 = (($Check1 >> 4) & 0x3C000) | ($Check1 & 0x3FFF);

        $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) << 2) | ($Check2 & 0xF0F);
        $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000);

        return ($T1 | $T2);
    }

    /* Generate a checksum for the hash */
    function CheckHash($Hashnum)
    {
        $CheckByte = 0;
        $Flag = 0;
        $HashStr = sprintf('%u', $Hashnum);
        $length = strlen($HashStr);
        for ($i = $length - 1; $i >= 0; $i--) {
            $Re = $HashStr{$i};
            if (1 === ($Flag % 2)) {
                $Re += $Re;
                $Re = (int)($Re / 10) + ($Re % 10);
            }
            $CheckByte += $Re;
            $Flag++;
        }
        $CheckByte %= 10;
        if (0 !== $CheckByte) {
            $CheckByte = 10 - $CheckByte;
            if (1 === ($Flag % 2)) {
                if (1 === ($CheckByte % 2)) {
                    $CheckByte += 9;
                }
                $CheckByte >>= 1;
            }
        }
        return '7' . $CheckByte . $HashStr;
    }

    /* .. */
    function StrToNum($Str, $Check, $Magic)
    {
        $Int32Unit = 4294967296; // 2^32
        $length = strlen($Str);
        for ($i = 0; $i < $length; $i++) {
            $Check *= $Magic;
            /*  If the float is beyond the boundaries of integer (usually +/- 2.15e+9 = 2^31),
                the result of converting to integer is undefined
                refer to http://www.php.net/manual/en/language.types.integer.php    */
            if ($Check >= $Int32Unit) {
                $Check = ($Check - $Int32Unit * (int)($Check / $Int32Unit));
                //if the check less than -2^31
                $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
            }
            $Check += ord($Str{$i});
        }
        return $Check;
    }
}