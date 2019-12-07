<?php
class GroupRollingCurl extends RollingCurl {

    private $group_callback = null;

    protected function process($output, $info, $request)
    {
        if( $request instanceof RollingCurlGroupRequest)
            $request->process($output, $info);

        if (is_callable($this->group_callback))
            call_user_func($this->group_callback, $output, $info, $request);
    }

    function __construct($callback = null)
    {
        $this->group_callback = $callback;

        parent::__construct(array(&$this, "process"));
    }

    public function add($request)
    {
        if ($request instanceof RollingCurlGroup)
            return $request->addToRC($this);
        else
            return parent::add($request);
    }

    public function execute($window_size = null) {

        if (count($this->requests) == 0)
            return false;

        return parent::execute($window_size);
    }

}
