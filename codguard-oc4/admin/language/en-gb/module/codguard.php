<?php
/**
 * CodGuard for OpenCart - Admin Language File (English)
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    1.0.0
 */

// Heading
$_['heading_title']    = 'CodGuard';

// Text
$_['text_home']        = 'Home';
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified CodGuard module!';
$_['text_edit']        = 'Edit CodGuard Module';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';

// Tabs
$_['tab_api_config']       = 'API Configuration';
$_['tab_order_status']     = 'Order Status Mapping';
$_['tab_payment_methods']  = 'Payment Methods';
$_['tab_rating_settings']  = 'Rating Settings';
$_['tab_statistics']       = 'Statistics';

// Entry
$_['entry_status']              = 'Status';
$_['entry_shop_id']             = 'Shop ID';
$_['entry_public_key']          = 'Public Key';
$_['entry_private_key']         = 'Private Key';
$_['entry_rating_tolerance']    = 'Rating Tolerance';
$_['entry_rejection_message']   = 'Rejection Message';
$_['entry_good_status']         = 'Successful Order Status';
$_['entry_refused_status']      = 'Refused Order Status';
$_['entry_cod_methods']         = 'Cash on Delivery Methods';

// Help
$_['help_shop_id']             = 'Your unique shop identifier from CodGuard.';
$_['help_public_key']          = 'Your API public key (minimum 10 characters).';
$_['help_private_key']         = 'Your API private key (minimum 10 characters). Keep this secure!';
$_['help_rating_tolerance']    = 'Customers with a rating below this threshold will not be able to use COD payment methods. Recommended: 30-40%.';
$_['help_rejection_message']   = 'This message will be displayed to customers whose rating is below the tolerance threshold.';
$_['help_good_status']         = 'Orders with this status will be reported as successful to CodGuard (outcome = 1).';
$_['help_refused_status']      = 'Orders with this status will be reported as refused to CodGuard (outcome = -1).';
$_['help_cod_methods']         = 'Select all payment methods that should trigger customer rating checks. Typically, this includes cash on delivery methods.';

// Error
$_['error_permission']         = 'Warning: You do not have permission to modify CodGuard module!';
$_['error_shop_id']            = 'Shop ID is required!';
$_['error_public_key']         = 'Public Key is required!';
$_['error_public_key_length']  = 'Public Key must be at least 10 characters long!';
$_['error_private_key']        = 'Private Key is required!';
$_['error_private_key_length'] = 'Private Key must be at least 10 characters long!';
