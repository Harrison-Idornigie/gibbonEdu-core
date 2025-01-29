<?php
namespace Gibbon\Module\ExtraReports\Hook;

use Gibbon\Module\ExtraReports\Extension\ReportRendererExtension;

class ReportTemplateHook
{
    protected $renderer;

    public function __construct($renderer = null)
    {
        $this->renderer = $renderer;
    }

    public function onReportGeneration(&$args)
    {
        // If we have a renderer, extend it with our custom functionality
        if ($this->renderer) {
            $extension = new ReportRendererExtension();
            $extension->extendRenderer($this->renderer);
        }
    }
}
