<?php

class Helper
{
    /*
     * checks the content of body returned by curl to see if is blocked by google
     * returns true/false;
     */
    public static function isBlocked($content)
    {
        $bad_cases = array(
            'computer virus or spyware application',
            'entire network is affected',
            'http://www.download.com/Antivirus',
            'the document has moved',
        );

        $blocked = false;
        foreach ($bad_cases as $b_no => $case) {
            if (stripos($content, $case) !== false) {
                echo "\n" . '! Info: A proxy-blocked pattern matched. We\'ll use next proxy!.' . "\n";
                $blocked = true;
            }
        }

        if ($blocked == false && strlen(trim($content)) == 0) {
            echo "\n" . '! Info: body is empty. We assume the proxy is blocked or failed to connect. We\'ll use next proxy!' . "\n";
            $blocked = true;
        }

        return $blocked;
    }

    public static function getCurrentProxyCount($fileName)
    {
        $count = 0;
        if (file_exists($fileName)) {
            $count = implode('', file($fileName));

            if (trim($count) == '') {
                $count = 0;
            }
        }

        return $count;
    }

    public static function incrementCurrentProxyCount($fileName, $no)
    {
        $fp = fopen($fileName, 'a+');

        $current = static::getCurrentProxyCount($fileName);

        ftruncate($fp, 0);
        fwrite($fp, $no + $current);

        echo ' - - - File contains #' . ($no + $current) . ' (info:' . $current . '+' . $no . ')' . "\n";
        fclose($fp);
    }

    public static function resetCurrentProxyCount($fileName)
    {
        $fp = fopen($fileName, 'a+');
        ftruncate($fp, 0);
        fwrite($fp, '0');
        fclose($fp);
    }
}
