<?php

class GoogleTrends extends CrawlerBase
{
    public $dbo, $type, $proxy_query, $keywords, $proxies, $crawledDate;

    function __construct( $type, $config )
    {
        parent::__construct( $config );

        // init db:
        $this->dbo = new DbHandle( $config );

        // set type:
        $this->type = $type;

        // settings:
        $this->max_results    = 30; // number of results from google
        $this->keywords_query = 'SELECT * FROM tbl_project_keywords';

        // settings based on type:
        switch ($this->type) {
            case 'rankalytics_crawler':
                $f_name = 'proxy_cs_trends';
                break;
            default:
                $f_name = 'proxy_cb_trends';
                break;
        }

        $this->proxy_count_file = $this->config['prj_path'] . '/stats/' . $f_name . '.txt';
        $offset                 = Helper::getCurrentProxyCount( $this->proxy_count_file );

        // ..
        $r = $this->dbo->getProxies( "SELECT * FROM proxy WHERE google_blocked='0'" );
        if ($offset >= count( $r )) {
            $offset = 0;
            Helper::resetCurrentProxyCount( $this->proxy_count_file );
        }

        $this->proxy_query = "SELECT * FROM proxy WHERE google_blocked='0' LIMIT 999999 OFFSET " . $offset;

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
        $this->keywords = $this->dbo->getKeywords( $this->keywords_query );
        if (count( $this->keywords ) == 0) {
            return false;
        }
        $this->proxies = $this->dbo->getProxies( $this->proxy_query );

        // curl static sets before work:
        SingleCurl::$dbo         = $this->dbo;
        SingleCurl::$proxy_count = 0;
        SingleCurl::$proxies     = $this->proxies;
        SingleCurl::$fileName    = $this->proxy_count_file;

        // ->
        $this->getTrends();

        //when we get here.. time for sleep / extra-sleep
        $sleep_time = rand( 25, 59 );
        echo 'Crawler is sleeping ' . $sleep_time . 's .. ' . "\n";
        sleep( $sleep_time );

        // todo - remove it:
        echo( 'GoogleTrends: finished' . "\n" );
    }

    public function getTrends()
    {
        $g_tld = $this->config['google_tld'];
        $g_geo = $this->config['google_geo'];

        foreach ($this->keywords as $k_no => $row) {
            $search_string = urlencode( $row['keyword'] );
            echo "Getting trends for: '" . $search_string . "'\n";

            #do curl:
            $config['url'] = 'http://www.google.' . $g_tld . '/trends/trendsReport?q=' . $search_string . '&geo=' . $g_geo;
            $body          = SingleCurl::action( $config );
            echo " -> " . $config['url'] . "\n";

            //save:
            $this->crawledDate = $row['crawled_date'];
            echo " saving.. ";
            $this->saveResultsUpdateQueries( $this->parseForTrends( $body, $search_string, $row ) );
            echo " saved.\n";

            Helper::incrementCurrentProxyCount( $this->proxy_count_file, SingleCurl::$proxy_count );

            /* NEXT PROXY FOR NEW KEYWORD! */
            SingleCurl::$proxy_count ++;
        }

        echo "Completed run.\n";
        sleep( rand( 25, 59 ) );
    }

    /* parses for trends: */
    public function parseForTrends( $content, $search_string, $row )
    {
        $result_array = array();

        if (preg_match( '#var chartData = (.*?); var #is', $content, $matches, PREG_OFFSET_CAPTURE )) {
            $match      = str_ireplace( array( 'new Date(', ')},' ), array( '"date:[', ']"},' ), $matches[1][0] );
            $data_array = json_decode( $match, true );

            //prepare $result_array:
            if (is_array( $data_array ) AND isset( $data_array['rows'] )) {
                foreach ($data_array['rows'] as $key => $value) {
                    $explode    = explode( "date:[", $value[0]['v'] );
                    $explode[1] = rtrim( $explode[1], ']' );

                    $date_explode    = explode( ",", $explode[1] );
                    $date_explode[1] = $date_explode[1] + 1;
                    $explode[1]      = trim( $date_explode[0] ) . "/" . trim( $date_explode[1] ) . "/" . trim( $date_explode[2] );

                    $date       = strtotime( $explode[1] );
                    $explode[1] = date( 'Y-m-d', $date );

                    $time           = date( "Y-m-d\TH:i:s" ) . substr( (string) microtime(), 1, 8 );
                    $hash_id        = substr( md5( $time . $search_string ), 0, 8 );
                    $date           = $this->crawledDate;
                    $result_array[] = "('" . $hash_id . "','" . $row['unique_id'] . "','" . $explode[1] . "','" . $value[3] . "', '" . $value[0]['f'] . "', '" . $date . "')";
                }
            }
        }

        return $result_array;
    }

    /* saves to db and updates: */
    public function saveResultsUpdateQueries( $result_array )
    {
        if (count( $result_array ) > 0) {
            $query = "INSERT INTO \"trend_data\" (id, keyword_id, trend_date, trend_count,trend_date_string,crawled_date) VALUES " . implode( ',', $result_array );
            $this->dbo->runQuery( $query );
        }
    }
}