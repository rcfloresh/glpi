<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

/**
 * Class which manages notification events
 */
class NotificationEvent extends CommonDBTM {

   static function dropdownEvents($itemtype,$value='') {
      $events = array();

      $target = NotificationTarget::getInstanceByType($itemtype);
      if ($target) {
         $events = $target->getEvents();
      }
      $events[''] = '-----';
      Dropdown::showFromArray('event',$events, array ('value'=>$value));
   }

   /**
    * Raise a notification event event
    * @param event the event raised for the itemtype
    * @param item the object which raised the event
    * @param options array options used
    */
   static function raiseEvent($event,$item,$options=array()) {
      global $CFG_GLPI, $DB;

      logDebug("raise event : $event");
      //If notifications are enabled in GLPI's configuration
      if ($CFG_GLPI["use_mailing"]) {

      $email_processed = array();
      $email_notprocessed = array();

      $notificationtarget = NotificationTarget::getInstance($item);
      $entity = $notificationtarget->getEntity();

/*
      //If event is raised by a plugin, load it in order to get the language file available
      if ($plug = isPluginItemType(get_class($item))) {
         Plugin::loadLang(strtolower($plug['plugin']));
      }
*/
         //Foreach notification
         foreach (Notification::getNotificationsByEventAndType($event,
                                                               $item->getType(),
                                                               $entity) as $data) {
            $targets = getAllDatasFromTable('glpi_notificationtargets',
                                            'notifications_id='.$data['id']);

            //Foreach notification targets
            foreach ($targets as $target) {
               $templates_id = $data['notificationtemplates_id'];

               $notificationtarget->clearAddressesList();
               //Get all users affected by this notification
               $notificationtarget->getAddressesByTarget($target,$options);

               //Get template's informations
               $template = new NotificationTemplate;

               //Set notification's signature (the one which corresponds to the entity)
               $template->setSignature(Notification::getMailingSignature($entity));
               $template->getFromDB($templates_id);

               foreach ($notificationtarget->getTargets() as $template_id => $users_infos) {

                  //If the user have not yet been notified
                  if (!isset($email_processed[$users_infos['language']])) {
                     //If ther user's language is the same as the template's one

                     if (isset($email_notprocessed[$users_infos['language']])) {
                        unset($email_notprocessed[$users_infos['language']]);
                     }

                     if ($template->getTemplateByLanguage($notificationtarget,
                                                          $users_infos['language'],
                                                          $event,
                                                          $options)) {
                        //Send notification to the user
                        Notification::send ($template->getDataToSend($notificationtarget,
                                                                     $users_infos['language'],
                                                                     $users_infos['email'],
                                                                     $options));

                        $email_processed[$users_infos['language']] = $users_infos;
                     }
                     else {
                        $email_notprocessed[$users_infos['language']] = $users_infos;
                     }
                  }
               }
            }
         }
      }

      return true;
   }

}
?>