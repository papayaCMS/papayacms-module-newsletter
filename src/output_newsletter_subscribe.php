<?php
/**
* Newsletter - viewer functionality
*
* @copyright 2002-2007 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license   papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package commercial
* @subpackage newsletter
* @version $Id: output_newsletter_subscribe.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Newsletter - base functionality
*/
require_once(dirname(__FILE__).'/base_newsletter.php');

/**
* Newsletter - genral functionality
*
* @package commercial
* @subpackage newsletter
*/
class output_newsletter_subscribe extends base_newsletter {

  /**
  * Get XML for registerform
  *
  * @param array $data
  * @access public
  * @return string XML
  */
  function getXMLRegisterForm ($data) {
    $string = '';
    $string .= sprintf(
      '<form action="%s" method="post">',
      papaya_strings::escapeHTMLChars($this->baseLink)
    );
    $string .= sprintf(
      '<input type="hidden" name="%s[action]" value="add_surfer"/>',
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    $string .= sprintf(
      '<input type="hidden" name="%s[newsletter_list_id]" value="%d"/>',
      papaya_strings::escapeHTMLChars($this->paramName),
      (int)$data['newsletter_list_id']
    );
    $string .= sprintf(
      '<field fid="salutation" caption="%s" width="10">'.LF,
      papaya_strings::escapeHTMLChars($data['cap_salutation'])
    );
    $string .= sprintf(
      '<select size="1" name="%s[salutation]">'.LF,
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    $select = (@$this->params['salutation'] == 0) ? ' selected="selected"' : '';
    $string .= sprintf(
      '<option value="0" %s>%s</option>'.LF,
      $select,
      papaya_strings::escapeHTMLChars($data['cap_salutation_male'])
    );
    $select = (@$this->params['salutation'] == 1) ? ' selected="selected"' : '';
    $string .= sprintf(
      '<option value="1" %s>%s</option>'.LF,
      $select,
      papaya_strings::escapeHTMLChars($data['cap_salutation_female'])
    );
    $string .= '</select>'.LF;
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['first_name']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="first_name" caption="%s" width="45" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_first_name']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[first_name]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['first_name'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['last_name']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="last_name" caption="%s" width="45" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_last_name']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[last_name]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['last_name'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['branch']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="branch" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_branch']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[branch]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['branch'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['firm']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="firm" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_firm']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[firm]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['firm'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['position']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="position" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_position']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[position]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['position'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['section']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="section" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_section']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[section]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['section'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['title']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="title" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_title']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[title]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['title'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['street']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="street" caption="%s" width="70" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_street']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[street]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['street'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['house_number']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="house_number" caption="%s" width="30" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_house_number']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[house_number]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['house_number'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['zip']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="zip" caption="%s" width="20" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_zip']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[zip]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['zip'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['city']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="city" caption="%s" width="80" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_city']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[city]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['city'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['phone']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="phone" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_telephone']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[phone]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['phone'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['mobile']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="mobile" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_mobile']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[mobil]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['mobil'])
    );
    $string .= '</field>'.LF;

    $error = ' error='. (isset($this->errors['fax']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="fax" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_fax']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[fax]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['fax'])
    );
    $string .= '</field>'.LF;
    $error = ' error='. (isset($this->errors['email']) ? '"yes"' : '"no"');

    $string .= sprintf(
      '<field fid="email" caption="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($data['cap_email']),
      $error
    );
    $string .= sprintf(
      '<input type="text" name="%s[email]" value="%s"/>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['email'])
    );
    $string .= '</field>'.LF;
    if ($data['checkbox'] == 1) {
      $string .= sprintf(
        '<field fid="newsletter" caption="%s">'.LF,
        papaya_strings::escapeHTMLChars($data['cap_send'])
      );
      $string .= sprintf(
        '<input class="checkBox" type="checkbox" name="%s[newsletter]"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $string .= '</field>'.LF;
    }

    $string .= sprintf(
      '<field fid="html_mode" caption="%s" width="10">'.LF,
      papaya_strings::escapeHTMLChars($data['cap_html_mode'])
    );
    $string .= sprintf(
      '<select size="1" name="%s[html_mode]">'.LF,
      papaya_strings::escapeHTMLChars($this->paramName)
    );

    $select = (@$this->params['html_mode'] == 1) ? ' selected="selected"' : '';
    $string .= sprintf(
      '<option value="1" %s>%s</option>'.LF,
      $select,
      papaya_strings::escapeHTMLChars($data['cap_html_mode_1'])
    );

    $select = (@$this->params['html_mode'] == 0) ? ' selected="selected"' : '';
    $string .= sprintf(
      '<option value="0" %s>%s</option>'.LF,
      $select,
      papaya_strings::escapeHTMLChars($data['cap_html_mode_2'])
    );

    $string .= '</select>'.LF;
    $string .= '</field>'.LF;

    $string .= sprintf('<field fid="submit">'.LF);
    $string .= sprintf(
      '<input type="submit" name="%s[submit]" value="%s" />',
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars($data['cap_submit'])
    );
    $string .= '</field>'.LF;
    $string .= '</form>'.LF;
    return $string;
  }
  /**
  * Get output for registerform
  *
  * @param array $data
  * @access public
  * @return string XML
  */
  function getOutput($data) {
    $this->initializeParams();
    $string = '';

    if (isset($this->params['action']) && $this->params['action'] == 'add_surfer') {
      if ($this->checkInputs()) {

        if ($this->surferExists($this->params['email'], $this->params['newsletter_list_id'])) {
          $this->loadOneSurfer($this->params['email'], $this->params['newsletter_list_id']);

          if (isset($this->oneSurfer) &&
              is_array($this->oneSurfer) &&
              (
                $this->oneSurfer['surfer_status'] == 0 ||
                $this->oneSurfer['surfer_status'] == 1 ||
                $this->oneSurfer['surfer_status'] == 4
               )
             ) {

            $this->params['surfer_status'] = (isset($this->params['newsletter'])) ? 1 : 0;

            if ($data['checkbox'] == 0) {
              $this->params['surfer_status'] = 1;
            }

            $this->saveSurfer();
            $this->correctProtocol();
            if (isset($this->params['newsletter']) || $data['checkbox'] == 0) {

              $activateCode = $this->sendMail(0, $data);
              $this->addRegisterToProtocol(0, $activateCode);
              $string .= sprintf('<msg>%s</msg>', $data['register_request']);

            } else {
              $string .= sprintf('<msg>%s</msg>', $data['register']);
              $this->addRegisterToProtocol(0);
            }
          } else {
            $string .= sprintf('<msg>%s</msg>', $data['email_exists' ]);
          }
        } else {

          $this->params['surfer_status'] = (isset($this->params['newsletter'])) ? 1 : 0;

          if ($data['checkbox'] == 0) {
            $this->params['surfer_status'] = 1;
          }

          if ($this->surferExists($this->params['email'], $this->params['newsletter_list_id'])) {
            $this->setHTMLMode(
              $this->params['email'],
              $this->params['newsletter_list_id'],
              $this->params['html_mode'],
              $data
            );
          } else {
            $this->addSurfer();
          }
          if (isset($this->params['newsletter']) || $data['checkbox'] == 0) {
            $activateCode = $this->sendMail(0, $data);
            $this->addRegisterToProtocol(0, $activateCode);
            $string .= sprintf('<msg>%s</msg>', $data['register_request']);
          } else {
            $string .= sprintf('<msg>%s</msg>', $data['register']);
            $this->addRegisterToProtocol(0);
          }
        }
      } else {
        $string .= sprintf('<msg>%s</msg>', $data['input_error']);
        $string .= $this->getXMLRegisterForm($data);
      }
    } elseif (isset($_GET['activate']) && $_GET['activate'] != '' &&
              $this->checkInputs()) {
      if ($this->activateSurfer($_GET['activate'], $_GET['email'], $data['newsletter_list_id'])) {
        $string .= sprintf('<msg>%s</msg>', $data['register_confirmed']);
      } else {
        $string .= sprintf('<msg>%s</msg>', $data['wrong_activate_code']);
      }
    } elseif (isset($_GET['action']) &&
              $_GET['action'] = 'switchmode' &&
              $this->checkInputs() &&
              isset($_GET['lid']) &&
              $_GET['lid'] != '') {
      $string .= $this->toggleHTMLMode($_GET['email'], (int)$_GET['lid'], $data);
    } else {
      $string .= $this->getXMLRegisterForm($data);
    }

    return $string;
  }
}
