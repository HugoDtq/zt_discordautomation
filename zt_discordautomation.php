<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Zt_discordautomation extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'zt_discordautomation';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'Agence Zetruc';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Discord Automation');
        $this->description = $this->l('Permet de transmettre des informations sur un serveur Discord par l\'intermédiaire d\'un Webhook prédéfini');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('ZT_DISCORDAUTOMATION_WEBHOOK', '');
        Configuration::updateGlobalValue('ZT_DISCORDAUTOMATION_TOKEN', md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME')));

        $this->_createTab();

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('ZT_DISCORDAUTOMATION_WEBHOOK');
        Configuration::deleteByName('ZT_DISCORDAUTOMATION_TOKEN');
        Configuration::deleteByName('ZT_CUSTOMERGROUP_TO_EXCLUDE');
        Configuration::deleteByName('ZT_COMMANDSTATUS_TO_EXCLUDE');
        Configuration::deleteByName('ZT_SOURCE_CHOICE');

        $this->_removeTab();

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $message = null;
        if (((bool)Tools::isSubmit('submitZt_discordautomationModule'))) {
                $webhook = strval(Tools::getValue('ZT_DISCORDAUTOMATION_WEBHOOK'));
                $groupToExclude = strval(Tools::getValue('ZT_CUSTOMERGROUP_TO_EXCLUDE'));
                $statusToExclude = strval(Tools::getValue('ZT_COMMANDSTATUS_TO_EXCLUDE'));
                $sourceChoice = intval(Tools::getValue('ZT_SOURCE_CHOICE'));
                if (!$webhook){
                    $message .= $this->displayError($this->l('Erreur lors de l\'enregistrement'));
                } else {
                Configuration::updateValue('ZT_DISCORDAUTOMATION_WEBHOOK', $webhook);
                Configuration::updateValue('ZT_CUSTOMERGROUP_TO_EXCLUDE',$groupToExclude);
                Configuration::updateValue('ZT_COMMANDSTATUS_TO_EXCLUDE',$statusToExclude);
                Configuration::updateValue('ZT_SOURCE_CHOICE',$sourceChoice);
                $message .= $this->displayConfirmation($this->l('Paramètres mis à jour'));
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $this->context->smarty->assign('controller_url', $this->context->link->getAdminLink('AdminDiscordAutomation', false));
        $this->context->smarty->assign('token', Configuration::get('ZT_DISCORDAUTOMATION_TOKEN'));
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $message.$output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitZt_discordautomationModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['ZT_DISCORDAUTOMATION_WEBHOOK'] = Configuration::get('ZT_DISCORDAUTOMATION_WEBHOOK');
        $helper->fields_value['ZT_CUSTOMERGROUP_TO_EXCLUDE'] = Configuration::get('ZT_CUSTOMERGROUP_TO_EXCLUDE');
        $helper->fields_value['ZT_COMMANDSTATUS_TO_EXCLUDE'] = Configuration::get('ZT_COMMANDSTATUS_TO_EXCLUDE');
        $helper->fields_value['ZT_SOURCE_CHOICE'] = Configuration::get('ZT_SOURCE_CHOICE');


        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $option = array(
            array(
                'id_option' => 1,
                'name' => 'CA commandé',
            ),
            array(
                'id_option' => 2,
                'name' => 'CA facturé',
            ),
        );

        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Configuration du webhook'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'name' => 'ZT_DISCORDAUTOMATION_WEBHOOK',
                        'label' => $this->l('Adresse du webhook'),
                        'required' => true,
                        'default' => Configuration::get('ZT_DISCORDAUTOMATION_WEBHOOK'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'ZT_CUSTOMERGROUP_TO_EXCLUDE',
                        'label' => $this->l('ID groupe client à exclure'),
                        'desc' => 'Mettre les id sans espaces séparés par des virgules',
                        'default' => Configuration::get('ZT_CUSTOMERGROUP_TO_EXCLUDE'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'ZT_COMMANDSTATUS_TO_EXCLUDE',
                        'label' => $this->l('ID status commande à exclure'),
                        'desc' => 'Mettre les id sans espaces séparés par des virgules',
                        'default' => Configuration::get('ZT_COMMANDSTATUS_TO_EXCLUDE'),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'ZT_SOURCE_CHOICE',
                        'label' => $this->l('Source de CA à prendre en compte'),
                        'options' => array(
                            'query' => $option,
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                        'default' => Configuration::get('ZT_SOURCE_CHOICE'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Sauvegarder'),
                ),
            ),
        );
    }

    private function _createTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminDiscordAutomation';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang)
            $tab->name[$lang['id_lang']] = 'Discord Automation';
        $tab->id_parent = -1;
        $tab->module = $this->name;
        $tab->add();
    }

    private function _removeTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminDiscordAutomation');
        if ($id_tab)
        {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
    }
}
