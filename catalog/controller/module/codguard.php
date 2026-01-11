<?php
/**
 * CodGuard for OpenCart 4.x - Catalog Controller
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.9.0
 */

namespace Opencart\Catalog\Controller\Extension\Codguard\Module;

class Codguard extends \Opencart\System\Engine\Controller {

    /**
     * Inject checkout validation JavaScript
     * Called via event hook on footer render
     */
    public function injectCheckoutScript(string &$route, array &$data, mixed &$output): void {
        // Only inject on checkout pages
        if (!isset($this->request->get['route']) || strpos($this->request->get['route'], 'checkout') === false) {
            return;
        }

        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        // Add external JavaScript file using document->addScript()
        $this->document->addScript('catalog/view/javascript/codguard.js');
    }
}
