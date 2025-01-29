<?php
namespace Gibbon\Module\ExtraReports\Extension;

use Gibbon\Module\Reports\Renderer\TcpdfRenderer;
use Gibbon\Module\Reports\Renderer\MpdfRenderer;

class ReportRendererExtension
{
    public function extendRenderer($renderer)
    {
        if ($renderer instanceof TcpdfRenderer) {
            $this->extendTcpdfRenderer($renderer);
        } elseif ($renderer instanceof MpdfRenderer) {
            $this->extendMpdfRenderer($renderer);
        }
    }

    private function extendTcpdfRenderer($renderer)
    {
        // Get the template from the renderer
        $template = $renderer->getTemplate();
        
        // Check if there's a custom paper size set
        $paperSize = $this->getCustomPaperSize($template->getData('gibbonReportTemplateID'));
        
        if ($paperSize) {
            // Override the page size
            $template->setData('pageSize', $paperSize);
            
            // If it's A3, set the correct dimensions
            if ($paperSize == 'A3') {
                $renderer->getPdf()->setPageFormat('A3', $template->getData('orientation', 'P'));
            }
        }
    }

    private function extendMpdfRenderer($renderer)
    {
        // Get the template from the renderer
        $template = $renderer->getTemplate();
        
        // Check if there's a custom paper size set
        $paperSize = $this->getCustomPaperSize($template->getData('gibbonReportTemplateID'));
        
        if ($paperSize) {
            // Override the page size
            $template->setData('pageSize', $paperSize);
            
            // If it's A3, set the correct dimensions (in mm)
            if ($paperSize == 'A3') {
                $format = [297, 420]; // A3 dimensions
                if ($template->getData('orientation', 'P') == 'L') {
                    $format = array_reverse($format);
                }
                $renderer->getMpdf()->_setPageSize($format, $template->getData('orientation', 'P'));
            }
        }
    }

    private function getCustomPaperSize($templateID)
    {
        global $pdo;

        $data = ['templateID' => $templateID];
        $sql = "SELECT paperSize FROM extraReportsPaperSize WHERE gibbonReportTemplateID=:templateID";
        $result = $pdo->selectOne($sql, $data);

        return $result['paperSize'] ?? null;
    }
}
