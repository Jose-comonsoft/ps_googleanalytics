<?php
/**
 * 2007-2020 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class Ps_Googleanalytics extends Module
{
    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    const PS_16_EQUIVALENT_MODULE = 'ganalytics';

    public $name;
    public $tab;
    public $version;
    public $ps_versions_compliancy;
    public $author;
    public $module_key;
    public $bootstrap;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $js_state = 0;
    public $eligible = 0;
    public $filterable = 1;
    public $products = [];
    public $_debug = 0;
    public $psVersionIs17;

    public function __construct()
    {
        $this->name = 'ps_googleanalytics';
        $this->tab = 'analytics_stats';
        $this->version = '4.1.0';
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->author = 'PrestaShop';
        $this->module_key = 'fd2aaefea84ac1bb512e6f1878d990b8';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Analytics');
        $this->description = $this->l('Gain clear insights into important metrics about your customers, using Google Analytics');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Google Analytics? You will lose all the data related to this module.');
        $this->psVersionIs17 = (bool) version_compare(_PS_VERSION_, '1.7', '>=');
    }

    /**
     * Back office module configuration page content
     */
    public function getContent()
    {
        $configurationForm = new PrestaShop\Module\Ps_Googleanalytics\Form\ConfigurationForm($this);
        $formOutput = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $formOutput = $configurationForm->treat();
        }

        $formOutput .= $configurationForm->generate();

        return $this->display(__FILE__, './views/templates/admin/configuration.tpl') . $formOutput;
    }

    public function hookDisplayHeader($params, $back_office = false)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayHeader($this, $this->context);
        $hook->setBackOffice($back_office);

        return $hook->run();
    }

    /**
     * Confirmation page hook.
     * This function is run to track transactions.
     */
    public function hookDisplayOrderConfirmation($params)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayOrderConfirmation($this, $this->context);
        $hook->setParams($params);

        return $hook->run();
    }

    /**
     * Footer hook.
     * This function is run to load JS script for standards actions such as product clicks
     */
    public function hookDisplayFooter()
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayFooter($this, $this->context);

        return $hook->run();
    }

    /**
     * Homepage hook.
     * This function is run to manage analytics for product list associated to home featured, news products and best sellers Modules
     */
    public function hookDisplayHome()
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayHome($this, $this->context);

        return $hook->run();
    }

    /**
     * Product page footer hook
     * This function is run to load JS for product details view
     */
    public function hookDisplayFooterProduct($params)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayFooterProduct($this, $this->context);
        $hook->setParams($params);

        return $hook->run();
    }

    /**
     * Hook admin order.
     * This function is run to send transactions and refunds details
     */
    public function hookDisplayAdminOrder()
    {
        $gaTagHandler = new PrestaShop\Module\Ps_Googleanalytics\Handler\GanalyticsJsHandler($this, $this->context);

        $output = $gaTagHandler->generate(
            $this->context->cookie->__get('ga_admin_refund'),
            true
        );
        $this->context->cookie->__unset('ga_admin_refund');
        $this->context->cookie->write();

        return $output;
    }

    /**
     * Admin office header hook.
     * This function is run to add Google Analytics JavaScript
     */
    public function hookDisplayBackOfficeHeader()
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookDisplayBackOfficeHeader($this, $this->context);

        return $hook->run();
    }

    /**
     * Product cancel action hook (in Back office).
     * This function is run to add Google Analytics JavaScript
     */
    public function hookActionProductCancel($params)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookActionProductCancel($this, $this->context);
        $hook->setParams($params);
        $hook->run();
    }

    /**
     * Hook called after order status change, used to "refund" order after cancelling it
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookActionOrderStatusPostUpdate($this, $this->context);
        $hook->setParams($params);
        $hook->run();
    }

    /**
     * Save cart event hook.
     * This function is run to implement 'add to cart' and 'remove from cart' functionalities
     */
    public function hookActionCartSave()
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookActionCartSave($this, $this->context);
        $hook->run();
    }

    public function hookActionCarrierProcess($params)
    {
        $hook = new PrestaShop\Module\Ps_Googleanalytics\Hooks\HookActionCarrierProcess($this, $this->context);
        $hook->setParams($params);
        $hook->run();
    }

    protected function _debugLog($function, $log)
    {
        if (!$this->_debug) {
            return true;
        }

        $myFile = _PS_MODULE_DIR_ . $this->name . '/logs/analytics.log';
        $fh = fopen($myFile, 'a');
        fwrite($fh, date('F j, Y, g:i a') . ' ' . $function . "\n");
        fwrite($fh, print_r($log, true) . "\n\n");
        fclose($fh);
    }

    /**
     * This method is triggered at the installation of the module
     * - it installs all module tables
     * - it registers the hooks used by this module
     *
     * @return bool
     */
    public function install()
    {
        $moduleHandler = new PrestaShop\Module\Ps_Googleanalytics\Handler\ModuleHandler();
        $database = new PrestaShop\Module\Ps_Googleanalytics\Database\Install($this);

        $moduleHandler->uninstallModule(self::PS_16_EQUIVALENT_MODULE);

        return parent::install() &&
            $database->registerHooks() &&
            $database->setDefaultConfiguration() &&
            $database->installTables();
    }

    /**
     * Triggered at the uninstall of the module
     * - erases this module SQL tables
     *
     * @return bool
     */
    public function uninstall()
    {
        $database = new PrestaShop\Module\Ps_Googleanalytics\Database\Uninstall();

        return parent::uninstall() &&
            $database->uninstallTables();
    }
}
