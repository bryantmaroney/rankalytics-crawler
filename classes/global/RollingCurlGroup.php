<?php
class RollingCurlGroup
{
    protected $name;
    protected $num_requests = 0;
    protected $finished_requests = 0;
    private $requests = array();

    function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return void
     */
    public function __destruct() {
        unset($this->name, $this->num_requests, $this->finished_requests, $this->requests);
    }


    function add($request)
    {
        if ($request instanceof RollingCurlGroupRequest)
        {
            $request->setGroup($this);
            $this->num_requests++;
            $this->requests[] = $request;
        }
        else if (is_array($request))
        {
            foreach ($request as $req)
                $this->add($req);
        }
        else
            throw new RollingCurlGroupException("add: Request needs to be of instance RollingCurlGroupRequest");

        return true;
    }

    function addToRC($rc)
    {
        $ret = true;

        if (!($rc instanceof RollingCurl))
            throw new RollingCurlGroupException("addToRC: RC needs to be of instance RollingCurl");

        while (count($this->requests) > 0)
        {
            $ret1 = $rc->add(array_shift($this->requests));
            if (!$ret1)
                $ret = false;
        }

        return $ret;
    }

    function process($output, $info, $request)
    {
        $this->finished_requests++;

        if ($this->finished_requests >= $this->num_requests)
            $this->finished();
    }

    function finished()
    {
    }

}