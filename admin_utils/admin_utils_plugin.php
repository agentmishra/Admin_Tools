<?php
/**
 * Admin Tools - Help Admins In thier Task .
 * 
 * @package blesta
 * @subpackage blesta.plugins.Admin Utils
 * @copyright Copyright (c) 2014, Naja7host SARL.
 * @link http://www.naja7host.com/ Naja7host
 */
class AdminUtilsPlugin extends Plugin {

	public function __construct() {
		Language::loadLang("admin_utils_plugin", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Load components required by this plugin
		Loader::loadComponents($this, array("Input"));
		
        // Set the company ID
        $this->company_id = Configure::get("Blesta.company_id");
		
        // Load modules for this plugun
        Loader::loadModels($this, array("ModuleManager", "Companies"));
		// Loader::loadModels($this, array("admin_utils.AdminSecurity"));
		$this->loadConfig(dirname(__FILE__) . DS . "config.json");
		
	}
	
    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $plugin_id The ID of the plugin being installed
     */
	public function install($plugin_id) {
		try {
			$value = array('ip_restriction' => false , 'block_access' => false , 'allowed_ips'=> null ,  'blocked_ips'=> null , 'uninstall_plugins'=> true , 'stopforumspam_check'=> true , 'block_duplicate'=> false , 'route_admin'=> null);
			$this->Companies->setSetting($this->company_id, "AdminUtilsPlugin", serialize($value) );
			
			// V 2.2.0
			Loader::loadModels($this, array("admin_utils.UtilInvoices"));			
			$proforma_id = $this->UtilInvoices->GetLastProformaID();
			$invoices = array('eu_invoicing' => false , 'last_proforma_id' => $proforma_id , /*'correct_dateinvoice' => false ,*/ 'save_invoice' => false );
			$this->Companies->setSetting($this->company_id, "AdminUtilsPluginInvoicing", serialize($invoices) );

		}
		catch (Exception $e) {
			// Error dropping... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
		}
	}
	
    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this plugin
     * @param int $plugin_id The ID of the plugin being upgraded
     */
	public function upgrade($current_version, $plugin_id) {
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
			// Upgrade to 1.8.0
			if (version_compare($current_version, "1.6.0", "<")) {
				// Add settings to databse 
				$value = array('ip_restriction' => false , 'block_access' => false , 'allowed_ips'=> null ,  'blocked_ips'=> null , 'uninstall_plugins'=> true , 'stopforumspam_check'=> true , 'block_duplicate'=> false , 'route_admin'=> null);
				$this->Companies->setSetting($this->company_id, "AdminUtilsPlugin", serialize($value) );				
			}

			// Upgrade to 2.2.0
			if (version_compare($current_version, "2.2.0", "<")) {
				Loader::loadModels($this, array("admin_utils.UtilInvoices"));			
				$proforma_id = $this->UtilInvoices->GetLastProformaID();
				$invoices = array('eu_invoicing' => false , 'last_proforma_id' => $proforma_id , /*'correct_dateinvoice' => false ,*/ 'save_invoice' => false);
				$this->Companies->setSetting($this->company_id, "AdminUtilsPluginInvoicing", serialize($invoices) );
			}
			
			// Upgrade to 2.3.0
			if (version_compare($current_version, "2.3.0", "<")) {
				$UtilSecuritySettings = $this->Companies->getSetting($this->company_id , "AdminUtilsPluginInvoicing");
				$invoices = unserialize($UtilSecuritySettings->value);				
				$new_set['save_invoice'] = false ; 
				$result = array_merge($invoices , $new_set);				
				$this->Companies->setSetting($this->company_id, "AdminUtilsPluginInvoicing", serialize($result) );
			}

		}
	}
	
    /**
     * Performs any necessary cleanup actions
     *
     * @param int $plugin_id The ID of the plugin being uninstalled
     * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
     */
	public function uninstall($plugin_id, $last_instance) {
		try {
			$this->Companies->unsetSetting($this->company_id , "AdminUtilsPlugin");
			$this->Companies->unsetSetting($this->company_id , "AdminUtilsPluginInvoicing");
		}
		catch (Exception $e) {
			// Error dropping... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
		}		
	}
	
    public function getEvents() {
        return array(
            array(
                'event' => "Appcontroller.structure",
                'callback' => array("this", "StructureAdminTools")
            ),		
			array(
                'event' => "Appcontroller.preAction",
                'callback' => array("this", "preActionAdminTools")
            ),
            array(
                'event' => "Invoices.add",
                'callback' => array("this", "InvoicesaddAdminTools")
            ), 
            // array(
                // 'event' => "Invoices.edit",
                // 'callback' => array("this", "InvoiceseditAdminTools")
            // ), 			
            array(
                'event' => "Invoices.setClosed",
                'callback' => array("this", "InvoicessetClosedAdminTools")
            )			
            // Add multiple events here
        );
    }
	
    public function preActionAdminTools($event) {

		/*  */

		Loader::loadModels($this, array("admin_utils.UtilSecurity"));		

		// Call Block IPS for Admin Side
		$this->UtilSecurity->ipRestrictions();

		// Block access for uninstalled plugins
		$this->UtilSecurity->UninstallPlugins();

		// Stop Spam users
		$this->UtilSecurity->StopSpam();

		// Block registration for duplicated email
		$this->UtilSecurity->BlockDuplicate();
		
		//print_r($this->post);
		// Add Language Selection  to clients
		$this->UtilSecurity->SelectLanguage();

		
	}	
 	
    public function StructureAdminTools($event) {
		// Nothing TODO NOW .
	}		

    public function InvoicesaddAdminTools($event) {
		Loader::loadModels($this, array("admin_utils.UtilInvoices"));		
		$params = $event->getParams();
		$invoice_id = $params['invoice_id'];
		$this->UtilInvoices->Invoicesadd($invoice_id);		
	}

    // public function InvoiceseditAdminTools($event) {

	// }
	
    public function InvoicessetClosedAdminTools($event) {
		Loader::loadModels($this, array("admin_utils.UtilInvoices"));		
		$params = $event->getParams();
		$invoice_id = $params['invoice_id'];
		$this->UtilInvoices->setClosed($invoice_id);
	}
	
    /**
     * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
     *
     * @return array A numerically indexed array containing:
     * 	- action The action to register for
     * 	- uri The URI to be invoked for the given action
     * 	- name The name to represent the action (can be language definition)
     */
    public function getActions() {
        return array(
            array(
                'action' => "nav_secondary_staff",
                'uri' => "plugin/admin_utils/admin_main/",
                'name' => Language::_("AdminToolsPlugin.title", true),
                'options' => array('parent' => "tools/")
            )
        );
    }
}
?>