<?php
namespace Controller;
class AnyCtrl extends AbstractCtrl
{
    public function render()
    {
        $output = '';
        $this->beforeRender();
        // PHP 8.4 compatible: Check for null before method_exists()
        if($this->view !== null && method_exists($this->view, 'output')) {
            $output = $this->view->output();
        }
        $this->afterRender();
        return $output;
    }

    protected function beforeRender()
    {
    }

    protected function afterRender()
    {
    }
}