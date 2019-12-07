<?php

class GoogleChart
{
    protected $data, $size, $reversed_values, $x_axis, $content, $project_name, $top_limit, $low_limit, $y_axis;

    function __construct(array $params)
    {
        $this->size = $params['size_w_h'];
        $this->data = $params['data'];
        $this->project_name = (!isset($params['project_name'])) ? null : $params['project_name'];
        $this->reversed_values = $this->x_axis = $this->y_axis = array();
        $this->content = '';

        // workflow
        $this->prepareData();
        $this->doCurl();
    }

    /* prepare some information for graph */
    protected function prepareData()
    {
        // sets:
        $this->top_limit = 1;
        $this->low_limit = 30;
        $this->y_axis = array(
            '30',
            '25',
            '20',
            '15',
            '10',
            '5',
            '1',
        ); //^ reversed order (= 1 will show at the top)

        /* handle values:
         * ! because the max limit is 1 (=100%) and low one is 30 (= 0%) => we need to reverse the corresponding values.
         * */
        foreach ($this->data as $v_no => $value) {
            if ($value == $this->top_limit) {
                $new_value = 100;
            } else if ($value == $this->low_limit) {
                $new_value = 0;
            } else {
                $new_value = 100 - $value * 50 / ((($this->low_limit > $this->top_limit) ? $this->low_limit : $this->top_limit) / 2);
            }

            // ..
            $this->reversed_values[] = number_format($new_value, 2);
            $this->x_axis[] = $v_no;
        }
    }


    /*
     * post information to google 's chart and save the content;
     * */
    protected function doCurl()
    {
        $config = array(
            'url' => 'https://chart.googleapis.com/chart',
            'header' => 0,
            'timeout' => 3,
            'agent' => 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.103 Safari/537.36',
            'post' => $this->postParams(),
        );

        $con = curl_init();
        curl_setopt($con, CURLOPT_TIMEOUT, $config['timeout']);
        curl_setopt($con, CURLOPT_HEADER, $config['header']);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($con, CURLOPT_URL, $config['url']);
        curl_setopt($con, CURLOPT_USERAGENT, $config['agent']);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($con, CURLOPT_POST, TRUE);
        curl_setopt($con, CURLOPT_POSTFIELDS, $config['post']);

        $this->content = curl_exec($con);
        curl_close($con);
    }

    /* POST information for curl ^ */
    protected function postParams()
    {
        $temp = array(
            'cht' => 'lc',
            'chtt' => 'RANKALYTICS.COM',
            'chs' => implode('x', $this->size),
            'chxt' => 'x,y',
            'chm' => 'B,DDDDDD50,0,0,0|o,333c46,0,-1,10',
            'chg' => '25,33.33',
            'chxl' => '0:|' . implode('|', $this->x_axis) . '|1:|' . implode('|', $this->y_axis),
            'chd' => 't:' . implode(',', $this->reversed_values),
            'chxs' => '0,000000,13,0,t|1,000000,13,0,t',
        );

        if ($this->project_name != null) {
            $temp['chtt'] .= '|' . $this->project_name;
        }

        return http_build_query($temp);
    }

    /*
    * returns the image as PNG from curl
    * */
    function getImage()
    {
        if (stripos($this->content, 'png') !== false) {
            header('content-type:image/png');
        }

        return $this->content;
    }
}

// test:

$params = array(
    'data' => array(
        '21/12' => '15',
        '22/12' => '3',
        '23/12' => '24',
        '24/12' => '27',
        '25/12' => '17',
        '26/12' => '3',
        '27/12' => '10',
    ),
    'size_w_h' => array(
        '750',
        '300',
    ),
    'project_name' => 'Some project',
);

$graph = new GoogleChart($params);
echo $graph->getImage();
