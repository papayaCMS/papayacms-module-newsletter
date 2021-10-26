<?php
/**
* Newsletter - unsubscribe viewer functionality
*
* @copyright 2010-2016 by dimensional GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license   papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package Papaya-Modules
* @subpackage Newsletter
* @version $Id: output_newsletter_unsubscribe.php 12 2014-02-20 11:30:17Z SystemVCS $
*/

/**
* Newsletter - base functionality
*/
require_once(dirname(__FILE__).'/base_newsletter.php');

/**
* Newsletter - genral functionality
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class output_newsletter_unsubscribe extends base_newsletter {

  /**
  * Get XML unregisterform
  *
  * @param string $token optional, default value = ''
  * @param string $email optional, default value = ''
  * @param array $data data from editfields
  * @access public
  * @return string XML
  */
  function getXMLUnregisterForm($token = '', $email = '', $data = array()) {
    $string = '';
    $string .= sprintf(
      '<form action="%s" method="post">',
      papaya_strings::escapeHTMLChars($this->baseLink)
    );
    $string .= sprintf(
      '<input type="hidden" name="%s[action]" value="remove_surfer"/>',
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    if ($email != '') {
      $string .= sprintf(
        '<input type="hidden" name="%s[email]" value="%s"/>',
        papaya_strings::escapeHTMLChars($this->paramName),
        @papaya_strings::escapeHTMLChars(@$this->params['email']));
      if ($token != '') {
        $string .= sprintf(
          '<input type="hidden" name="%s[token]" value="%s"/>',
          papaya_strings::escapeHTMLChars($this->paramName),
          papaya_strings::escapeHTMLChars($token)
        );
        if (isset($this->surferNewsletters)) {
          $nlXML = '';
          foreach ($this->surferNewsletters as $newsletter) {
            $nlXML .= sprintf(
              '<input type="checkbox" name="%s[newsletter_list_id][]" value="%d">%s</input>',
              papaya_strings::escapeHTMLChars($this->paramName),
              (int)$newsletter['newsletter_list_id'],
              papaya_strings::escapeHTMLChars($newsletter['newsletter_list_description'])
            );
          }
          if ($nlXML != '') {
            $string .= sprintf('<field fid="newsletters">%s</field>', $nlXML);
          }
        }
      }
    } else {
      $string .= sprintf(
        '<field fid="email" caption="%s"><input type="text" name="%s[email]" /></field>',
        @papaya_strings::escapeHTMLChars($data['cap_email']),
        papaya_strings::escapeHTMLChars($this->paramName)
      );
    }
    $string .= sprintf(
      '<field fid="submit" caption="%s"><input type="submit" name="%s[submit]" /></field>',
      @papaya_strings::escapeHTMLChars($data['cap_submit']),
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    $string .= '</form>'.LF;
    return $string;
  }

  /**
  * Get output for unsubscribe
  *
  * @param array $data data from editfields
  * @access public
  * @return string XML
  */
  function getOutput($data) {
    $this->initializeParams();
    $string = '';
    if (isset($this->params['action']) &&
        $this->params['action'] == 'remove_surfer') {
      if (isset($this->params['email']) && !isset($this->params['token'])) {
        $this->loadSurferNewsletters($this->params['email']);
        if (!isset($this->surferNewsletters) ||
            !is_array($this->surferNewsletters)) {
          $string .= sprintf('<msg>%s</msg>', $data['no_newsletters']);
          return $string;
        }
        $token = $this->existingTokenByEmail($this->params['email']);
        if (!$token || $token == '') {
          srand((double)microtime() * 1000000);
          crypt(uniqid(rand()));
          $activateCode = substr(crypt(uniqid(rand())), 3, 10);
          $this->addRegisterToProtocol(-1, $activateCode);
        }
        $token = $this->existingTokenByEmail($this->params['email']);
        $unsubscribeLink = $this->getAbsoluteURL(
          $this->getWebLink($this->module->parentObj->topicId));
        $unsubscribeLink .= $this->recodeQueryString(
          sprintf(
            '?%s[action]=remove_surfer&%s[token]=%s&%s[email]=%s',
            $this->paramName,
            $this->paramName,
            $token,
            $this->paramName,
            $this->params['email']
          )
        );
        $emailObj = new email;
        $emailObj->setTemplate(
          'body',
          $data['mail_message2'],
          array('UNSUBSCRIBE_LINK' => $unsubscribeLink)
        );
        $emailObj->setHeader(
          'from', $data['addresser_name'].'<'.$data['mail_from'].'>'
        );

        if (!$emailObj->send($this->params['email'], $data['mail_subject2'])) {
          $this->logMsg(
            MSG_ERROR,
            8,
            'Unsubscription email could not be sent. Method sys_email::send() failed.',
            'email failed'
          );
        }
        $string .= sprintf(
          '<msg>%s</msg>', papaya_strings::escapeHTMLChars($data['unregister_email_sent'])
        );
        unset($this->module->data['show']);
        return $string;
      }
      if (isset($this->params['email']) && isset($this->params['token'])) {
        if ($this->surferExistsByToken(
            $this->params['email'], $this->params['token'])) {
          $this->loadOneSurferByEmail($this->params['email']);
        }
        if (isset($this->oneSurfer) && is_array($this->oneSurfer)) {
          if (isset($this->params['newsletter_list_id']) &&
              is_array($this->params['newsletter_list_id'])) {
            // de-registration
            $changed = 0;
            foreach ($this->params['newsletter_list_id'] as $nwlId) {
              if ($this->addRegisterToProtocol(1, $this->params['token'], $nwlId, time())) {
                if ($this->deactivateSurfer(
                      $this->params['token'], $this->params['email'], (int)$nwlId
                    )) {
                  $changed++;
                }
              }
            }
            if ($changed == 0) {
              $string .= sprintf(
                '<msg>%s</msg>',
                papaya_strings::escapeHTMLChars($data['wrong_deactivate_code'])
              );
            } else {
              $string .= sprintf(
                '<msg>%s</msg>',
                papaya_strings::escapeHTMLChars($data['unregister_confirmed'])
              );
            }
            $this->removeTokenByEmail($this->oneSurfer['email']);
          } elseif ($this->existingTokenByEmail($this->params['email']) ==
                      $this->params['token']) {
            $this->loadSurferNewsletters($this->params['email']);
            $this->module->data['show'] = 'text_choose';
          }
          $string .= $this->getXMLUnregisterForm(
            $this->params['token'], $this->params['email'], $data
          );
        } else {
          if (isset($this->params['email']) && !isset($this->params['token'])) {
            $string .= $this->getXMLUnregisterForm(
              NULL, $this->params['email'], $data
            );
          } else {
            $this->module->data['show'] = 'text';
            $string .= $this->getXMLUnregisterForm(NULL, NULL, $data);
          }
        }
      } else {
        $string .= sprintf('<msg>%s</msg>', $data['input_error']);
        $string .= $this->getXMLUnregisterForm(NULL, NULL, $data);
      }
    } else {
      $string .= $this->getXMLUnregisterForm(NULL, NULL, $data);
      $this->module->data['show'] = 'text';
    }
    return $string;
  }

}
