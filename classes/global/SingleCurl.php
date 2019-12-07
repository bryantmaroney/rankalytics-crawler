<?php

/*
 * Developed by B.O.A.
 * Used by GoogleSites
 * */

class SingleCurl
{
    public static $proxies, $proxy_count, $checkBlocking = true, $fileName, $dbo;

    /* this function takes of crawler, so that in case the proxy list is finished => sleeping */
    public static function checkProxyExistence()
    {
        //if we are out of proxies in this current session:
        if (!array_key_exists(static::$proxy_count, static::$proxies)) {
            echo "No more not-blocked proxies in this list/offset.\n";

            echo "- Reset proxy index\n";
            static::$proxy_count = 0;

            echo '- Reset file content' . "\n";
            Helper::resetCurrentProxyCount(static::$fileName);

            $sleep_time = 60*5;
            echo "- Crawler is sleeping " . $sleep_time . "s \n";
            sleep($sleep_time);
        }
    }

    /*
     * recursive method if checkBlocking = true
     * it will increment the proxy count in case it's blocked
    */
    public static function action($config)
    {
        //echo "#####.".static::$proxy_count."###<br/>\n\n\n\n\n";

        // check proxy existence before set/s
        static::checkProxyExistence();

        // sets:
        $proxy = static::$proxies[static::$proxy_count];

        // default:
        $config['agent'] = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.16) Gecko/20080702 Firefox/2.0.0.16";
        $config['header'] = 0;
        $config['timeout'] = 15; // seconds

        // init:
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        curl_setopt($ch, CURLOPT_HEADER, $config['header']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $config['url']);
        curl_setopt($ch, CURLOPT_USERAGENT, $config['agent']);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        //proxy zone:
        curl_setopt($ch, CURLOPT_PROXYTYPE, 'HTTP');
        curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']);
        if (trim($proxy['username']) != "" AND trim($proxy['password']) != "") {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, trim($proxy['username']) . ':' . trim($proxy['password']));
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        }

        $data = curl_exec($ch);
        curl_close($ch);

        if (static::$checkBlocking) {
            //^ this is true by default:

            if (Helper::isBlocked($data)) {
                echo "^ " . $proxy['id'] . " - " . $proxy['ip'] . " | static: " . static::$proxy_count . "\n";

                //update status to blocked:
                # static::$dbo->updateProxyById($proxy['id'], '1');

                //increment current used proxy to get a fresh one:
                static::$proxy_count++;

                //re-do action:
                return static::action($config);
            } else {
                return $data;
            }
        }

        return $data;
    }
}