CREATE TABLE `papaya_newsletter_subscribers` (
`subscriber_id` BIGINT NOT NULL AUTO_INCREMENT,
`subscriber_email` VARCHAR( 200 ) NOT NULL ,
`subscriber_salutation` INT NOT NULL ,
`subscriber_title` VARCHAR( 50 ) NOT NULL ,
`subscriber_firstname` VARCHAR( 40 ) NOT NULL ,
`subscriber_lastname` VARCHAR( 40 ) NOT NULL ,
`subscriber_branch` VARCHAR( 100 ) NOT NULL ,
`subscriber_company` VARCHAR( 100 ) NOT NULL ,
`subscriber_position` VARCHAR( 50 ) NOT NULL ,
`subscriber_section` VARCHAR( 50 ) NOT NULL ,
`subscriber_street` VARCHAR( 100 ) NOT NULL ,
`subscriber_housenumber` VARCHAR( 20 ) NOT NULL ,
`subscriber_postalcode` VARCHAR( 10 ) NOT NULL ,
`subscriber_city` VARCHAR( 50 ) NOT NULL ,
`subscriber_phone` VARCHAR( 50 ) NOT NULL ,
`subscriber_mobile` VARCHAR( 50 ) NOT NULL ,
`subscriber_fax` VARCHAR( 50 ) NOT NULL ,
`subscriber_data` TEXT NOT NULL ,
PRIMARY KEY ( `subscriber_id` ) ,
UNIQUE ( `subscriber_email` )
) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;

INSERT INTO papaya_newsletter_subscribers
  (subscriber_email, subscriber_salutation, subscriber_title, 
   subscriber_firstname, subscriber_lastname, subscriber_branch, 
   subscriber_company, subscriber_position, subscriber_section, 
   subscriber_street, subscriber_housenumber,
   subscriber_postalcode, subscriber_city, 
   subscriber_phone, subscriber_mobile, subscriber_fax) 
SELECT DISTINCT email, salutation, title, first_name, last_name, branch, firm, position, section, 
  street, house_number, zip, city, phone, mobil, fax
FROM papaya_newsletter_surfer
GROUP BY email;

CREATE TABLE `papaya_newsletter_subscriptions` (
`subscriber_id` BIGINT NOT NULL ,
`newsletter_list_id` BIGINT NOT NULL ,
`subscription_status` BIGINT NOT NULL ,
`subscription_format` BIGINT NOT NULL ,
PRIMARY KEY ( `subscriber_id`, `newsletter_list_id` )
) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;

INSERT INTO papaya_newsletter_subscriptions
  (subscriber_id, newsletter_list_id, subscription_status, subscription_format)
SELECT DISTINCT sub.subscriber_id, sur.newsletter_list_id, sur.surfer_status, sur.html_mode
  FROM papaya_newsletter_subscribers AS sub, papaya_newsletter_surfer AS sur
 WHERE sur.email = sub.subscriber_email;
 
CREATE TABLE `papaya_newsletter_protocol_tmp` (
  `protocol_id` bigint(20) NOT NULL auto_increment,
  `newsletter_list_id` int(11) NOT NULL default '0',
  `subscriber_id` bigint(20) NOT NULL default '',
  `protocol_created` bigint(20) NOT NULL default '0',
  `protocol_confirmed` bigint(20) NOT NULL default '0',
  `protocol_action` smallint(6) NOT NULL default '0',
  `activate_code` varchar(10) NOT NULL default '',
  PRIMARY KEY  (`protocol_id`),
  KEY (`subscriber_id`, `newsletter_list_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO papaya_newsletter_protocol_tmp
  (newsletter_list_id, subscriber_id, protocol_created, protocol_confirmed, protocol_action, activate_code)
SELECT p.newsletter_list_id, s.subscriber_id, 
  p.protocol_created, p.protocol_confirmed, p.protocol_action, p.activate_code
FROM papaya_newsletter_protocol AS p, papaya_newsletter_subscribers AS s
WHERE s.subscriber_email = p.protocol_email;

RENAME TABLE papaya_newsletter_protocol TO backup_papaya_newsletter_protocol, 
  papaya_newsletter_protocol_tmp TO papaya_newsletter_protocol;

ALTER TABLE `papaya_newsletter_lists`
  DROP `newsletter_mailinglist_name`,
  DROP `newsletter_mailinglist_name_html`;
  
  
-- aenderungen nummer 1.5
  
ALTER TABLE `papaya_newsletter_mailings` CHANGE `mailings_id` `mailing_id` BIGINT( 20 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE `papaya_newsletter_mailingcontent` CHANGE `mailings_id` `mailing_id` BIGINT( 20 ) NOT NULL DEFAULT '0';
ALTER TABLE `papaya_newsletter_mailingoutput` CHANGE `mailings_id` `mailing_id` BIGINT( 20 ) NOT NULL DEFAULT '0';

ALTER TABLE `papaya_newsletter_mailings` ADD `mailing_note` TEXT NOT NULL ;
  
  
-- aenderungen nummer 2
CREATE TABLE `papaya_newsletter_mailinggroups` (
  `mailinggroup_id` int(11) NOT NULL auto_increment,
  `mailinggroup_title` varchar(200) NOT NULL default '',
  `mailinggroup_default_textview` int(11) NOT NULL default '0',
  `mailinggroup_default_htmlview` int(11) NOT NULL default '0',
  `mailinggroup_default_intro` text NOT NULL,
  `mailinggroup_default_footer` text NOT NULL,
  `mailinggroup_default_intro_nl2br` int(11) NOT NULL default '0',
  `mailinggroup_default_footer_nl2br` int(11) NOT NULL default '0',
  PRIMARY KEY  (`mailinggroup_id`),
  KEY `mailinggroup_title` (`mailinggroup_title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `papaya_newsletter_mailinggroups` ADD `lng_id` INT NOT NULL AFTER `mailinggroup_title` ;

ALTER TABLE `papaya_newsletter_mailings` ADD `mailinggroup_id` INT NOT NULL AFTER `mailing_id`;

ALTER TABLE `papaya_newsletter_mailings` ADD `mailing_intro_nl2br` INT NOT NULL AFTER `mailing_footer` ,
ADD `mailing_footer_nl2br` INT NOT NULL AFTER `mailing_intro_nl2br` ;

UPDATE `papaya_newsletter_mailings` SET `mailing_intro_nl2br` = `nl2br`, `mailing_footer_nl2br` = `nl2br`;

ALTER TABLE `papaya_newsletter_mailings` DROP `nl2br`;

ALTER TABLE `papaya_newsletter_mailingcontent` CHANGE `nl2br` `mailingcontent_nl2br` SMALLINT( 6 ) NOT NULL DEFAULT '0';

ALTER TABLE `papaya_newsletter_mailinggroups` ADD `mailinggroup_default_subject` VARCHAR( 200 ) NOT NULL ,
ADD `mailinggroup_default_sender` VARCHAR( 200 ) NOT NULL ,
ADD `mailinggroup_default_senderemail` VARCHAR( 200 ) NOT NULL ;

ALTER TABLE `papaya_newsletter_protocol` ADD `subscriber_data` TEXT NOT NULL AFTER `protocol_action` ;