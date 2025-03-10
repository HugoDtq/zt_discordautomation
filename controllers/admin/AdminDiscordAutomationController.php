<?php

class AdminDiscordAutomationController extends ModuleAdminController
{
    private $last_monday;
    private $last_sunday;
    private $first_day_of_month;

    public function __construct()
    {
        parent::__construct();
        $token_input = filter_input(INPUT_GET, 'secure_key', FILTER_SANITIZE_STRING);
        if ($token_input !== Configuration::getGlobalValue('ZT_DISCORDAUTOMATION_TOKEN')){
            die('Bad secure key');
        }

        $today = new DateTime();
        $this->last_sunday = date('Y-m-d 23:59:59',strtotime('last sunday', $today->getTimestamp()));
        $this->last_monday = date('Y-m-d 00:00:00',strtotime('last monday', strtotime('last sunday', $today->getTimestamp())));
        $this->first_day_of_month = date('Y-m-d 00:00:00',strtotime('first day of this month', $today->getTimestamp()));

        $this->sendToDiscord();
        die();
    }

    private function sendToDiscord()
    {
        $webhook = Configuration::get('ZT_DISCORDAUTOMATION_WEBHOOK');
        if (!$webhook){
            die('Bad config');
        }
        $content = array (
            'content' => 'Chiffres d\'affaires',
            'embeds' => [
                [
                    'type' => 'rich',
                    'title' => Configuration::get('PS_SHOP_NAME'),
                    'description' => '',
                    'fields' =>
                        [
                            [
                                'name' => 'Semaine dernière :',
                                'value' => $this->getTurnoverBetweenDates($this->last_monday,$this->last_sunday),
                            ],
                            [
                                'name' => 'Mois en cours :',
                                'value' => $this->getTurnoverBetweenDates($this->first_day_of_month,$this->last_sunday),
                            ],
                        ],
                ],
            ],
        );

        $this->sendMessage($content, $webhook);


    }

    private function getTurnoverBetweenDates($date1,$date2)
    {
        try{
            $source = Configuration::get('ZT_SOURCE_CHOICE') && Configuration::get('ZT_SOURCE_CHOICE') == 1 ? 'date_add' : 'invoice_date';

            $sql = new DbQuery();
            $sql->select('CONCAT(FORMAT(SUM(total_paid_tax_excl), 2, "de_DE"), " € HT")');
            $sql->from('orders');
            $sql->where($source." BETWEEN '".$date1."' AND '".$date2."'");

            $excludeCommands = Configuration::get('ZT_COMMANDSTATUS_TO_EXCLUDE');
            if ($excludeCommands) {
                $commandToExclude = $this->stringToArray($excludeCommands);
                $sql->where('current_state NOT IN (' . implode(',', $commandToExclude) . ')');
            }

            $excludeGroups = Configuration::get('ZT_CUSTOMERGROUP_TO_EXCLUDE');
            if ($excludeGroups) {
                $groupToExclude = $this->stringToArray($excludeGroups);
                $sql->where('id_customer NOT IN (SELECT id_customer FROM ' . _DB_PREFIX_ . 'customer WHERE id_default_group IN (' . implode(',', $groupToExclude) . '))');
            }

            $sqlString = $sql->build();

            $data  = Db::getInstance()->executeS($sqlString);

            return strval(reset($data[0]));
        }
        catch (PrestaShopException $exception){
            die('Error database');
        }

    }

    private function sendMessage($mess, $webhook){
        if($webhook != "") {
            $ch = curl_init($webhook);
            $msg = json_encode($mess);

            if(isset($ch)) {
                try{
                    $headers = [ 'Content-Type: application/json; charset=utf-8' ];
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    echo($result);

                }
                catch (Exception $e){
                    return  $e;
                }
            } else{

                die('No CURL');
            }

        } else {
            die('No webhook');
        }
    }

    private function stringToArray($inputString) {

        $array = explode(',', $inputString);

        $array = array_map('intval', $array);

        return $array;
    }

}