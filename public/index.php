<?php

use WirecardCEE\Validator\CustomerId;

$fStartTime = microtime(true);
require __DIR__ . '/inc/server.constants.include.php';
require __DIR__ . '/inc/settings.inc.php';
require __DIR__ . '/inc/libvoyager.inc.php';
require __DIR__ . '/inc/qpay.var.include.php';
require __DIR__ . '/inc/qpay.autoloader.include.php';
require __DIR__ . '/inc/qpay.function.include.php';
require __DIR__ . '/inc/eps.function.php';
require __DIR__ . '/inc/qpay.validation.include.php';
require __DIR__ . '/inc/qpay.multilanguage.constants.php';
require __DIR__ . '/inc/qpay.multilanguage.include.php';
require __DIR__ . '/inc/PaymentTypeList.class.php';
require __DIR__ . '/inc/LanguageList.class.php';
require __DIR__ . '/inc/Zf2Singleton.php';
require_once __DIR__ . '/inc/logging.function.include.php';

$qpay = array();
$page = "INIT";
$qpay['LAST_VISITED_PAGE'] = 'init.php';
doLog($page, $_SERVER["SERVER_NAME"], "Got initiation V3: " . serialize(sanitizeArray($_POST)));

$qpay["Hostname"] = $_SERVER["SERVER_NAME"];
$qpay["InitVersion"] = "3.0";
$qpay['ProductVersion'] = $VERSION;
$qpay['Googlepay_Gateway'] = $GOOGLEPAY_GATEWAY;
$qpay['Googlepay_GatewayMerchantID'] = $GOOGLEPAY_GATEWAYMERCHANTID;
$qpay['Googlepay_environmentVar'] = $GOOGLEPAY_ENVIRONMENTVAR;
$qpay['isSeamless'] = false;

// divide posted data into different areas
$qpay["additionalParameters"] = array();

// if none of the following params were sent, assume param is highest permission possible
//
// permissions get retricted later when we read merchantConfig
// Examples
// D: default value, P: value sent to init as parameter, M: value set in merchant contig
// D: Edit, P: Edit, M: No -> No
// D: Edit, P: Yes, M: Edit -> Yes 
$paramDisplayBasketData = $merchantConfigPermissions->{yne('Y')};
$paramDisplayShippingData = $merchantConfigPermissions->{yne('E')};
$paramDisplayBillingData = $merchantConfigPermissions->{yne('E')};

// define some arrays for fpo/fp validating
$mandatoryFields = array();
$optionalFields = array();

// add the secret in every case as mandatory key
$mandatoryFields["SECRET"] = true;

// do some internal stuff preventing the merchant from using reserved parameter names
$reservedWords = $RESERVED_WORDS_V3;
$reservedWordsUsed = array();

// define an emtpy errorcode list as default. errors will be appended in this list
$errorCodes = array();
// define an empty paySysMessage list. Format of an entry should be like: $paySysMessages[$errorCode] = $paySysMessage
$paySysMessages = array();

// recoverable error: if first non recoverable occure, the script will stay in this page in every case
$errorIsRecoverable = true;

// warnings for journal
$warningMessageForJournal = "";
$initParamsForJournal = array();
$basket = array();
$recipient = array();
$consumer = array();
$globalParameter = array();

foreach ($_POST as $key => $value) {
    $originalKey = $key;
    $key = strtoupper($key);

    // removed get_magic_quotes_gpc invalid as of PHP 8. returns false since 5.4
    // kept in comment because, well you never know which abomination of PHP this might
    // run on in the future...
    //
    // if (get_magic_quotes_gpc()) {
    //     // removing added magic quotes
    //     $value = getCleanValue($key, $value);
    // }

    $initParamsForJournal[$originalKey] = $value;

    switch ($key) {
        case "SUBMIT_X":
        case "SUBMIT_Y":
        case "SUBMIT":
            break;
        case "CUSTOMERID":
            $qpay["customerId"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "SHOPID":
            $qpay["shopId"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'LAYOUT':
            $qpay['layout'] = strtoupper($value);
            break;
        case "AMOUNT":
            $qpay["amount"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "AMOUNT_NET":
            $qpay["amount_net"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CURRENCY":
            $qpay["currency"] = strtoupper($value);
            $mandatoryFields[$key] = true;
            break;
        case "PAYMENTTYPE":
            $qpay["paymentType"] = strtoupper($value);
            break;
        case "FINANCIALINSTITUTION":
            $qpay["financialInstitution"] = $value;
            break;
        case "LANGUAGE":
            $qpay["language"] = strtolower($value);
            break;
        case "ORDERDESCRIPTION":
            $qpay["orderDescription"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "DISPLAYTEXT":
            $qpay["displayText"] = $value;
            break;
        case "CSSURL":
            $qpay["cssUrl"] = $value;
            $globalParameter['cssUrl'] = $value;
            break;
        case "SUCCESSURL":
            $qpay["successUrl"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "FAILUREURL":
            $qpay["failureUrl"] = $value;
            break;
        case "CANCELURL":
            $qpay["cancelUrl"] = $value;
            break;
        case "PENDINGURL":
            $qpay["pendingUrl"] = $value;
            break;
        case "SERVICEURL":
            $qpay["serviceUrl"] = $value;
            break;
        case "CONFIRMURL":
            $qpay["confirmUrl"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONFIRMMAIL":
            $qpay['confirmMail'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "MERCHANTTOKENIZATIONFLAG":
            $qpay["merchantTokenizationFlag"] = $value;
            $globalParameter['merchantTokenizationFlag'] = $value;
            break;
        case "PERIODICTYPE":
            $qpay["periodicType"] = $value;
            $globalParameter['periodicType'] = $value;
            break;
        case "ISOTRANSACTIONTYPE":
            $qpay["isoTransactionType"] = $value;
            $globalParameter['isoTransactionType'] = $value;
            break;
        case "IMAGEURL":
            $qpay["imageUrl"] = $value;
            break;
        case "NOSCRIPTINFOURL":
            $qpay["noScriptInfoUrl"] = $value;
            break;
        case "ORDERNUMBER":
            $qpay["orderNumber"] = $value;
            break;
        case "CONSUMERBILLINGFIRSTNAME":
            $qpay["consumerBillingFirstName"] = $value;
            $consumer['billingaddress']['firstname'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGLASTNAME":
            $qpay["consumerBillingLastName"] = $value;
            $consumer['billingaddress']['lastname'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGADDRESS1":
            $qpay["consumerBillingAddress1"] = $value;
            $consumer['billingaddress']['address1'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGADDRESS2":
            $qpay["consumerBillingAddress2"] = $value;
            $consumer['billingaddress']['address2'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGADDRESS3":
            $qpay["consumerBillingAddress3"] = $value;
            $consumer['billingaddress']['address3'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGCITY":
            $qpay["consumerBillingCity"] = $value;
            $consumer['billingaddress']['city'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGZIPCODE":
            $qpay["consumerBillingZipCode"] = $value;
            $consumer['billingaddress']['zipcode'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGSTATE":
            $qpay["consumerBillingState"] = $value;
            $consumer['billingaddress']['state'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGCOUNTRY":
            $qpay["consumerBillingCountry"] = $value;
            $consumer['billingaddress']['country'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGPHONE":
            $qpay["consumerBillingPhone"] = $value;
            $consumer['billingaddress']['phone'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGFAX":
            $qpay["consumerBillingFax"] = $value;
            $consumer['billingaddress']['fax'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBILLINGMOBILEPHONE":
            $qpay["consumerBillingMobilePhone"] = $value;
            $consumer['billingaddress']['mobilePhone'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGFIRSTNAME":
            $qpay["consumerShippingFirstName"] = $value;
            $consumer['shippingaddress']['firstname'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGLASTNAME":
            $qpay["consumerShippingLastName"] = $value;
            $consumer['shippingaddress']['lastname'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGADDRESS1":
            $qpay["consumerShippingAddress1"] = $value;
            $consumer['shippingaddress']['address1'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGADDRESS2":
            $qpay["consumerShippingAddress2"] = $value;
            $consumer['shippingaddress']['address2'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGCITY":
            $qpay["consumerShippingCity"] = $value;
            $consumer['shippingaddress']['city'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGZIPCODE":
            $qpay["consumerShippingZipCode"] = $value;
            $consumer['shippingaddress']['zipcode'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGSTATE":
            $qpay["consumerShippingState"] = $value;
            $consumer['shippingaddress']['state'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGCOUNTRY":
            $qpay["consumerShippingCountry"] = $value;
            $consumer['shippingaddress']['country'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGPHONE":
            $qpay["consumerShippingPhone"] = $value;
            $consumer['shippingaddress']['phone'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGFAX":
            $qpay["consumerShippingFax"] = $value;
            $consumer['shippingaddress']['fax'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGADDRESSFIRSTUSEDATE":
            $qpay["consumerShippingAddressFirstUseDate"] = $value;
            $consumer['shippingaddress']['addressFirstUseDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGMETHOD":
            $qpay["consumerShippingMethod"] = $value;
            $consumer['shippingaddress']['method'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSHIPPINGITEMAVAILABILITY":
            $qpay["consumerShippingItemAvailability"] = $value;
            $consumer['shippingaddress']['itemAvailability'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTCREATIONDATE":
            $qpay["consumerAccountCreationDate"] = $value;
            $consumer['account']['creationDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTPASSWORDCHANGEDATE":
            $qpay["consumerAccountPasswordChangeDate"] = $value;
            $consumer['account']['passwordChangeDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTPURCHASESPASTSIXMONTHS":
            $qpay["consumerAccountPurchasesPastSixMonths"] = $value;
            $consumer['account']['purchasesPastSixMonths'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTUPDATEDATE":
            $qpay["consumerAccountUpdateDate"] = $value;
            $consumer['account']['updateDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTTRANSACTIONSPASTDAY":
            $qpay["consumerAccountAccountPastDay"] = $value;
            $consumer['account']['transactionsPastDay'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERACCOUNTTRANSACTIONSPASTYEAR":
            $qpay["consumerAccountAccountPastYear"] = $value;
            $consumer['account']['transactionsPastYear'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMEREMAIL":
            $qpay["consumerEmail"] = $value;
            $consumer['email'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERBIRTHDATE":
            $qpay["consumerBirthDate"] = $value;
            $consumer['birthdate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERTAXIDENTIFICATIONNUMBER":
            $qpay["consumerTaxIdentificationNumber"] = $value;
            $consumer['taxidentificationnumber'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERMERCHANTCRMID":
            $qpay["consumerMerchantCrmId"] = $value;
            $consumer['merchantcrmid'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERDEVICEID":
            $qpay["consumerDeviceId"] = $value;
            $consumer['deviceid'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERAUTHENTICATIONMETHOD":
            $qpay['consumerAuthenticationMethod'] = $value;
            $consumer['authenticationMethod'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERAUTHENTICATIONTIMESTAMP":
            $qpay['consumerAuthenticationTimestamp'] = $value;
            $consumer['authenticationTimestamp'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERCARDPROVISIONDATE":
            $qpay['consumerCardProvisionDate'] = $value;
            $consumer['cardProvisionDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERCARDPROVISIONATTEMPTSPASTDAY":
            $qpay['consumerCardProvisionPastDay'] = $value;
            $consumer['cardProvisionAttemptsPastDay'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERCHALLENGEINDICATOR":
            $qpay['consumerChallengeIndicator'] = $value;
            $consumer['challengeIndicator'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERSUSPICIOUSACTIVITY":
            $qpay['consumerSuspiciousActivity'] = $value;
            $consumer['suspiciousActivity'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERDELIVERYTIMEFRAME":
            $qpay['consumerDeliveryTimeframe'] = $value;
            $consumer['deliveryTimeframe'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERPREORDERDATE":
            $qpay['consumerPreorderDate'] = $value;
            $consumer['preorderDate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERREORDERITEMS":
            $qpay['consumerReorderItems'] = $value;
            $consumer['reorderItems'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERDRIVERSLICENSENUMBER":
            $qpay["consumerDriversLicenseNumber"] = $value;
            $consumer['driverslicense']['number'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERDRIVERSLICENSESTATE":
            $qpay["consumerDriversLicenseState"] = $value;
            $consumer['driverslicense']['state'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CONSUMERDRIVERSLICENSECOUNTRY":
            $qpay["consumerDriversLicenseCountry"] = $value;
            $consumer['driverslicense']['country'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'COMPANYNAME':
            $qpay['companyName'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'COMPANYVATID':
            $qpay['companyVatId'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'COMPANYTRADEREGISTRYNUMBER':
            $qpay['companyTradeRegistryNumber'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'COMPANYREGISTERKEY':
            $qpay['companyRegisterKey'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'RECIPIENTBIRTHDATE':
            $qpay['recipientBirthDate'] = $value;
            $recipient['recipientbirthdate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'RECIPIENTACCOUNT':
            $qpay['recipientAccount'] = $value;
            $recipient['recipientaccount'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'RECIPIENTZIPCODE':
            $qpay['recipientZipCode'] = $value;
            $recipient['recipientzipcode'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'RECIPIENTLASTNAME':
            $qpay['recipientLastName'] = $value;
            $recipient['recipientlastname'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'BASKETAMOUNT':
            $qpay['basketAmount'] = $value;
            $basket['amount'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'BASKETCURRENCY':
            $qpay['basketCurrency'] = $value;
            $basket['currency'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'BASKETITEMS':
            $qpay['basketItems'] = $value;
            $basket['items'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxARTICLENUMBER
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}ARTICLENUMBER$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -13));
            $qpay['basketItem'][$itemPosition]['articleNumber'] = $value;
            $basket['item'][$itemPosition]['articleNumber'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxQUANTITY
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}QUANTITY$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -8));
            $qpay['basketItem'][$itemPosition]['quantity'] = $value;
            $basket['item'][$itemPosition]['quantity'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxUNITPRICE
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}UNITPRICE$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -9));
            $qpay['basketItem'][$itemPosition]['unitPrice'] = $value;
            $basket['item'][$itemPosition]['unitPrice'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxTAX
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}TAX$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -3));
            $qpay['basketItem'][$itemPosition]['tax'] = $value;
            $basket['item'][$itemPosition]['tax'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxDESCRIPTION
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}DESCRIPTION$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -11));
            $qpay['basketItem'][$itemPosition]['description'] = $value;
            $basket['item'][$itemPosition]['description'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxIMAGEURL
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}IMAGEURL$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -8));
            $qpay['basketItem'][$itemPosition]['imageUrl'] = $value;
            $basket['item'][$itemPosition]['imageUrl'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxUNITGROSSAMOUNT
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}UNITGROSSAMOUNT$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -15));
            $qpay['basketItem'][$itemPosition]['unitGrossAmount'] = $value;
            $basket['item'][$itemPosition]['unitGrossAmount'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxUNITNETAMOUNT
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}UNITNETAMOUNT$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -13));
            $qpay['basketItem'][$itemPosition]['unitNetAmount'] = $value;
            $basket['item'][$itemPosition]['unitNetAmount'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxUNITTAXAMOUNT
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}UNITTAXAMOUNT$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -13));
            $qpay['basketItem'][$itemPosition]['unitTaxAmount'] = $value;
            $basket['item'][$itemPosition]['unitTaxAmount'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxUNITTAXRATE
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}UNITTAXRATE$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -11));
            $qpay['basketItem'][$itemPosition]['unitTaxRate'] = $value;
            $basket['item'][$itemPosition]['unitTaxRate'] = $value;
            $mandatoryFields[$key] = true;
            break;
        //BASKETITEMxNAME
        case (preg_match('/^BASKETITEM[1-9]{1}[0-9]{0,}NAME$/', $key) ? true : false):
            $itemPosition = intval(substr($key, 10, -4));
            $qpay['basketItem'][$itemPosition]['name'] = $value;
            $basket['item'][$itemPosition]['name'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "SHIPPINGPROFILE":
            $qpay['shippingProfile'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "REQUESTFINGERPRINT":
            $qpay["requestFingerprint"] = $value;
            break;
        case "DUPLICATEREQUESTCHECK":
            $qpay["duplicateRequestCheck"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "REQUESTFINGERPRINTORDER":
            $qpay["requestFingerprintOrder"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "WINDOWNAME":
            $qpay["windowName"] = $value;
            break;
        case "BACKGROUNDCOLOR":
            $qpay["backgroundColor"] = $value;
            break;
        case "ORDERREFERENCE":
            $qpay["orderReference"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "CUSTOMERSTATEMENT":
            $qpay["customerStatement"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "AUTODEPOSIT":
            $qpay["autoDeposit"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "RISKSUPPRESS":
            $qpay["riskSuppress"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "RISKCONFIGALIAS":
            $qpay['riskConfigAlias'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "MAXRETRIES":
            $qpay["maxRetries"] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'PAYMENTTYPESORTORDER':
            $qpay['paymentTypeSortOrder'] = $value;
            break;
        case "PLUGINVERSION":
            $qpay["pluginVersion"] = $value;
            break;
        case 'SOURCEORDERNUMBER':
            $qpay['sourceOrderNumber'] = $value;
            break;
        case 'MANDATEID':
            $qpay["mandateId"] = $value;
            break;
        case 'MANDATESIGNATUREDATE':
            $qpay["mandateSignatureDate"] = $value;
            break;
        case 'CREDITORID':
            $qpay["creditorId"] = $value;
            break;
        case 'DUEDATE':
            $qpay["dueDate"] = $value;
            break;
        case 'TRANSACTIONIDENTIFIER':
            $qpay['transactionIdentifier'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTID':
            $qpay['submerchant']['id'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTNAME':
            $qpay['submerchant']['name'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTCOUNTRY':
            $qpay['submerchant']['country'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTSTATE':
            $qpay['submerchant']['state'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTSTREET':
            $qpay['submerchant']['street'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'SUBMERCHANTZIPCODE':
            $qpay['submerchant']['zipCode'] = $value;
            break;
        case 'SUBMERCHANTCITY':
            $qpay['submerchant']['city'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case 'ALLOWEDCARDNETWORKS':
            $qpay['allowedCardNetworks'] = $value;
            $mandatoryFields[$key] = true;
            break;
        case "SECRET":
            $post = array_change_key_case($_POST);
            $customer = isset($post["customerid"]) ? $post["customerid"] : "???";
            $customer .= isset($post["shopid"]) ? $post["shopid"] : "";
            doLog($page, "WARN", "Got SECRET in request from [" . $customer . "]");
            $warningMessageForJournal .= "Got SECRET in request. ";
            break;
        case 'DISPLAYBASKETDATA':
            $paramDisplayBasketData = $merchantConfigPermissions->{yne($value)};
            break;
        case 'DISPLAYSHIPPINGDATA':
            $paramDisplayShippingData = $merchantConfigPermissions->{yne($value)};
            break;
        case 'DISPLAYBILLINGDATA':
            $paramDisplayBillingData = $merchantConfigPermissions->{yne($value)};
            break;
        default:
            if (in_array($key, $reservedWords)) {
                $reservedWordsUsed[] = $key;
            } else {
                $qpay["additionalParameters"][$originalKey] = $value;
            }
            break;
    }
}

// we have to use an empty default-value for shopId if not given
if (!isset($qpay["shopId"])) {
    $qpay["shopId"] = "";
}

if (!isset($qpay["financialInstitution"])) {
    $qpay["financialInstitution"] = "";
}

if (!(isset($qpay["customerId"]) && trim($qpay["customerId"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + CUSTOMERID;
    $errorIsRecoverable = false;
} else {
    $oCustomerId = new CustomerId();
    // validate customerid
    if ($oCustomerId->isValid(strtoupper($qpay['customerId']))) {
        // validate shopid
        // allowed is a-z, A-Z, 0-9, -
        $pattern = "/^([A-Za-z-0-9]{0,16})$/";
        if (preg_match($pattern, $qpay["shopId"])) {
            // read the merchant configuration from db.
            $qpay["merchantInformation"] = getMerchantInformation($qpay["customerId"], $qpay["shopId"]);
            $qpay["whitelabelConfig"] = getMerchantWhitelabelConfig($qpay["customerId"], $qpay["shopId"]);
            Zf2Singleton::setLegacyConfig(array(
                Zf2Singleton::LEGACY_CONFIG_PAGE => $page,
                Zf2Singleton::LEGACY_CONFIG_MERCHANT_INFORMATION => $qpay['merchantInformation']
            ));
            if (isset($qpay["merchantInformation"]["ERROR"])) {
                if ($qpay["merchantInformation"]["ERROR"] == "NO_CONFIGURATION") {
                    $errorCodes[] = PARAMETER_INVALID + CUSTOMERID;
                    $errorCodes[] = PARAMETER_INVALID + SHOPID;
                    $errorIsRecoverable = false;
                    unset($qpay["merchantInformation"]);
                } elseif ($qpay["merchantInformation"]["ERROR"] == "NO_DB_ACCESS") {
                    // unable to read the merchant information from the db
                    $errorCodes[] = FATAL_CANNOT_CONTINUE;
                    $errorIsRecoverable = false;
                    unset($qpay["merchantInformation"]);
                }
            } else {
                // we can extend the page information
                $page .= "[" . $qpay["merchantInformation"]["identifier"] . "]";

                // validate the paymentType against merchant configuration
                if (!(isset($qpay["paymentType"]) && trim($qpay["paymentType"]) != "")) {
                    $errorCodes[] = PARAMETER_MISSING + PAYMENTTYPE;
                } else {
                    //before we do overwrites we have to store the given request-paymentType
                    $qpay['requestPaymentType'] = $qpay['paymentType'];
                    if (strtoupper($qpay["paymentType"]) == 'SEPA-DD') {
                        $qpay["paymentType"] = 'ELV';
                    }
                    if (strtoupper($qpay["paymentType"]) == 'TRUSTLY') {
                        $qpay["paymentType"] = 'INSTANTBANK';
                    }

                    $bAllowFinancialInstitutionUsage = false;
                    if (strtoupper($qpay["paymentType"]) == 'MAESTRO') {
                        $qpay["paymentType"] = 'CCARD';
                        doLog($page, "WARN", "Merchant used deprecated payment type MAESTRO.");
                        $qpay["financialInstitution"] = "MAESTRO";
                        $bAllowFinancialInstitutionUsage = true;
                    }

                    $pType = $qpay["paymentType"];
                    $availableTypes = $qpay["merchantInformation"]["paysysConfig"];
                    if (array_key_exists($pType, $availableTypes)) {
                        if (isset($qpay["financialInstitution"]) && trim($qpay["financialInstitution"]) != "") {
                            if ($pType === "EPS" || $pType === "IDL") {
                                $fit = mapLegacyBanks($pType, $qpay['financialInstitution']);
                                $qpay["financialInstitution"] = $fit;

                                $availableFits = $availableTypes[$pType];
                                $found = false;
                                if ("EPS" === $pType && "EPS-SO" === $fit && isOverEE($qpay)) {
                                    $found = true;
                                }
                                foreach ($availableFits as $configFit) {
                                    doLog("DEBUG", "DEBUG", "Comparing: " . $fit . " <-> " . $configFit["GATEWAY"]);
                                    if (strcasecmp($fit, $configFit['GATEWAY']) == 0) {
                                        $found = true;
                                        break;
                                    }
                                }

                                if (!$found) {
                                    if (PaymentTypeList::isFinancialInstitution($qpay['financialInstitution'], $pType)) {
                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                    } else {
                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                    }
                                }
                            } elseif ('TRUSTPAY' == $pType) {
                                if (PaymentTypeList::isFinancialInstitution($qpay['financialInstitution'], $pType, false,
                                    $availableTypes['TRUSTPAY'][0]['DISPLAY_OPTIONS']['countryWhitelist'])
                                ) {
                                    $found = true;
                                    //check if paymentType is valid but not active due to countryWhitelist restrictions
                                } elseif (PaymentTypeList::isFinancialInstitution($qpay['financialInstitution'], $pType)) {
                                    //Country is not allowed in configuration
                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                    //check if list actually is available or if service is not reachable
                                } elseif (count(PaymentTypeList::getFinancialInstitutions($pType))) {
                                    $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                } else { //looks like no financialInstitutions are currently available for TRUSTPAY. Log error and proceed
                                    doLog($page, 'WARN',
                                        sprintf(
                                            'financialInstitution %s can not be validated because getFinancialInstitutions returns 0 banks.',
                                            $qpay['financialInstitution']
                                        )
                                    );
                                }
                            } elseif ($pType == 'MASTERPASS') {
                            } elseif ($pType == 'CCARD') {
                                if ($qpay["merchantInformation"]["control"]['ccardFinancialInstitution'] == 'Y' || $bAllowFinancialInstitutionUsage) {
                                    doLog($page, 'NONE', 'Trying to use following FITs: ' . $qpay['financialInstitution']);
                                    $merchantConfigCcardFitList = array();
                                    $allowedCcardFitList = array();
                                    $availableCcardFits = $availableTypes[$pType];
                                    foreach ($availableCcardFits as $ccardFit) {
                                        $merchantConfigCcardFitList[strtolower($ccardFit['BRAND'])] = $ccardFit;
                                    }
                                    $financialInstitutions = explode(',', $qpay['financialInstitution']);
                                    $useAmex = false;
                                    $useAmexSk = false;
                                    $useMC = false;
                                    $useMcsc = false;
                                    $useVisa = false;
                                    $useVbv = false;
                                    $useDiners = false;
                                    $useDinersClubProtectBuy = false;
                                    $useDiscover = false;
                                    $useDiscoverProtectBuy = false;
                                    $fitIndex = array();
                                    foreach ($financialInstitutions as $fit) {
                                        //ignore cases (but only for switch, not for logging
                                        $fit = trim($fit); // remove possible whitespaces in given list
                                        $lfit = strtolower($fit);
                                        switch ($lfit) {
                                            case 'amex':
                                                $useAmex = true;
                                                //only use amex configuration if not already set by amex secure key
                                                if (!isset($fitIndex['amex'])) {
                                                    if (isset($merchantConfigCcardFitList['amex'])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['amex'];
                                                        $fitIndex['amex'] = count($allowedCcardFitList) - 1;
                                                        // 3DS enabled
                                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][0] = 'N';
                                                        // 3DS only -> no fallback; issueDate for Maestro
                                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][1] = 'N';
                                                        // CVC Fallback; fallback for Maestro
                                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][2] = 'N';
                                                        doLog($page, 'NONE', 'Setting Amex SSL only.');
                                                    } else {
                                                        doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    if ($useAmex == false) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=AMEX.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                }
                                                break;
                                            case 'aesk':
                                            case 'amexsafekey':
                                            case 'amex safekey':
                                            case 'american express safekey':
                                                if (isset($merchantConfigCcardFitList['amex'])) {
                                                    if ($useAmexSk == true) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=AMEX.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    //mc is not configured as mcsc. We can't overwrite this
                                                    if ($merchantConfigCcardFitList['amex']['OPTIONS'][0] == 'N') {
                                                        doLog($page, 'NONE', 'Merchant is not configured for Amex SafeKey, FIT-upgrade not possible.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if (isset($fitIndex['amex'])) {
                                                        //mastercard already added. replace with mcsc configuration
                                                        $allowedCcardFitList[$fitIndex['amex']] = $merchantConfigCcardFitList['amex'];
                                                    } else {
                                                        //add new fit to allowedCcardFits
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['amex'];
                                                        $fitIndex['amex'] = count($allowedCcardFitList) - 1;
                                                    }
                                                    $useAmexSk = true;
                                                } else {
                                                    doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'diners':
                                                $useDiners = true;
                                                //only use diners configuration if not already set by Dinersclubprotectbuy
                                                if (!isset($fitIndex[$lfit])) {
                                                    if (isset($merchantConfigCcardFitList[$lfit])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList[$lfit];
                                                        $fitIndex[$lfit] = count($allowedCcardFitList) - 1;
                                                        // 3DS enabled
                                                        $allowedCcardFitList[$fitIndex[$lfit]]['OPTIONS'][0] = 'N';
                                                        // 3DS only -> no fallback; issueDate for Maestro
                                                        $allowedCcardFitList[$fitIndex[$lfit]]['OPTIONS'][1] = 'N';
                                                        // CVC Fallback; fallback for Maestro
                                                        $allowedCcardFitList[$fitIndex[$lfit]]['OPTIONS'][2] = 'N';
                                                        doLog($page, 'NONE', 'Setting ' . $lfit . ' SSL only.');
                                                    } else {
                                                        doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    if ($useDinersClubProtectBuy == false) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=DinersClubProtectBuy.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                }
                                                break;
                                            case 'dcpb':
                                            case 'diners protectbuy':
                                            case 'dinersprotectbuy':
                                                if (isset($merchantConfigCcardFitList['diners'])) {
                                                    if ($useDinersClubProtectBuy == true) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=Diners.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    // diners is not configured as diners protectbuy. We can't overwrite this
                                                    if ($merchantConfigCcardFitList['diners']['OPTIONS'][0] == 'N') {
                                                        doLog($page, 'NONE', 'Merchant is not configured for Diners ProtectBuy, FIT-upgrade not possible.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if (isset($fitIndex['diners'])) {
                                                        // diners already added. replace with diners protectbuy configuration
                                                        $allowedCcardFitList[$fitIndex['diners']] = $merchantConfigCcardFitList['diners'];
                                                    } else {
                                                        // add new fit to allowedCcardFits
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['diners'];
                                                        $fitIndex['diners'] = count($allowedCcardFitList) - 1;
                                                    }
                                                    $useDinersClubProtectBuy = true;
                                                } else {
                                                    doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'discover':
                                                $useDiscover = true;
                                                //only use discover configuration if not already set by discover protectbuy
                                                if (!isset($fitIndex['discover'])) {
                                                    if (isset($merchantConfigCcardFitList['discover'])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['discover'];
                                                        $fitIndex['discover'] = count($allowedCcardFitList) - 1;
                                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][0] = 'N';
                                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][1] = 'N';
                                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][2] = 'N';
                                                        doLog($page, 'NONE', 'Setting Discover SSL only.');
                                                    } else {
                                                        doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    if ($useDiscoverProtectBuy == false) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=Discover.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                }
                                                break;
                                            case 'dpb':
                                            case 'discoverprotectbuy':
                                            case 'discover protectbuy':
                                                if (isset($merchantConfigCcardFitList['discover'])) {
                                                    if ($useDiscoverProtectBuy == true) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=Discover.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    // diners is not configured as diners protectbuy. We can't overwrite this
                                                    if ($merchantConfigCcardFitList['discover']['OPTIONS'][0] == 'N') {
                                                        doLog($page, 'NONE', 'Merchant is not configured for Discover ProtectBuy, FIT-upgrade not possible.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if (isset($fitIndex['discover'])) {
                                                        // discover already added. replace with discover protectbuy configuration
                                                        $allowedCcardFitList[$fitIndex['discover']] = $merchantConfigCcardFitList['discover'];
                                                    } else {
                                                        // add new fit to allowedCcardFits
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['discover'];
                                                        $fitIndex['discover'] = count($allowedCcardFitList) - 1;
                                                    }
                                                    $useDiscoverProtectBuy = true;
                                                } else {
                                                    doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'maestro':
                                            case 'uatp':
                                                if (isset($merchantConfigCcardFitList[$lfit])) {
                                                    //only add fit if not yet added
                                                    if (!isset($fitIndex[$lfit])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList[$lfit];
                                                        $fitIndex[$lfit] = count($allowedCcardFitList) - 1;
                                                    } else {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=' . $fit . '.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'mastercard':
                                            case 'mc':
                                                $useMC = true;
                                                //only use mc configuration if not already set by mcsc
                                                if (!isset($fitIndex['mc'])) {
                                                    if (isset($merchantConfigCcardFitList['mc'])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['mc'];
                                                        $fitIndex['mc'] = count($allowedCcardFitList) - 1;
                                                        // 3DS enabled
                                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][0] = 'N';
                                                        // 3DS only -> no fallback; issueDate for Maestro
                                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][1] = 'N';
                                                        // CVC Fallback; fallback for Maestro
                                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][2] = 'N';
                                                        doLog($page, 'NONE', 'Setting MC SSL only.');
                                                    } else {
                                                        doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    if ($useMcsc == false) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=MC.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                }
                                                break;
                                            case 'mcsc':
                                            case 'mastercardsecurecode':
                                            case 'mastercard securecode':
                                                if (isset($merchantConfigCcardFitList['mc'])) {
                                                    if ($useMcsc == true) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=MC.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    //mc is not configured as mcsc. We can't overwrite this
                                                    if ($merchantConfigCcardFitList['mc']['OPTIONS'][0] == 'N') {
                                                        doLog($page, 'NONE', 'Merchant is not configured for MCSC, FIT-upgrade not possible.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if (isset($fitIndex['mc'])) {
                                                        //mastercard already added. replace with mcsc configuration
                                                        $allowedCcardFitList[$fitIndex['mc']] = $merchantConfigCcardFitList['mc'];
                                                    } else {
                                                        //add new fit to allowedCcardFits
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['mc'];
                                                        $fitIndex['mc'] = count($allowedCcardFitList) - 1;
                                                    }
                                                    $useMcsc = true;
                                                } else {
                                                    doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'visa':
                                                $useVisa = true;
                                                //only use visa configuration if not already set by vbv
                                                if (!isset($fitIndex['visa'])) {
                                                    if (isset($merchantConfigCcardFitList['visa'])) {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['visa'];
                                                        $fitIndex['visa'] = count($allowedCcardFitList) - 1;
                                                        // 3DS enabled
                                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][0] = 'N';
                                                        // 3DS only -> no fallback; issueDate for Maestro
                                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][1] = 'N';
                                                        // CVC Fallback; fallback for Maestro
                                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][2] = 'N';
                                                        doLog($page, 'NONE', 'Setting Visa SSL only.');
                                                    } else {
                                                        doLog($page, 'NONE', 'Brand ' . $fit . ' is not configured for this merchant.');
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                } else {
                                                    if ($useVbv == false) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=Visa.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                }
                                                break;
                                            case 'vbv':
                                            case 'verifiedbyvisa':
                                            case 'verified by visa':
                                                if (isset($merchantConfigCcardFitList['visa'])) {
                                                    if ($useVbv == true) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=Visa.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if ($merchantConfigCcardFitList['visa']['OPTIONS'][0] == 'N') {
                                                        doLog(
                                                            $page,
                                                            'NONE',
                                                            'Merchant is not configured for VbV, FIT-upgrade not possible.'
                                                        );
                                                        $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                        break 2;
                                                    }
                                                    if (isset($fitIndex['visa'])) {
                                                        //visa already added. replace with vbv configuration
                                                        $allowedCcardFitList[$fitIndex['visa']] = $merchantConfigCcardFitList['visa'];
                                                    } else {
                                                        $allowedCcardFitList[] = $merchantConfigCcardFitList['visa'];
                                                        $fitIndex['visa'] = count($allowedCcardFitList) - 1;
                                                    }
                                                    $useVbv = true;
                                                } else {
                                                    doLog(
                                                        $page,
                                                        'NONE',
                                                        'Brand ' . $fit . ' is not configured for this merchant.'
                                                    );
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                break;
                                            case 'jcb':
                                                if (!isset($merchantConfigCcardFitList['jcb'])) {
                                                    doLog(
                                                        $page,
                                                        'NONE',
                                                        'Brand ' . $fit . ' is not configured for this merchant.'
                                                    );
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                if (isset($fitIndex['jcb'])) {
                                                    if ('N' === $allowedCcardFitList[$fitIndex['jcb']]['OPTIONS'][0]) {
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=JCB.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    } else {
                                                        // Nothing to do.
                                                        // J/Secure is already added.
                                                    }
                                                } else {
                                                    $allowedCcardFitList[] = $merchantConfigCcardFitList['jcb'];
                                                    $fitIndex['jcb'] = count($allowedCcardFitList) - 1;
                                                    $allowedCcardFitList[$fitIndex['jcb']]['OPTIONS'][0] = 'N';
                                                    $allowedCcardFitList[$fitIndex['jcb']]['OPTIONS'][1] = 'N';
                                                }
                                                break;
                                            case 'j/secure':
                                            case 'jsecure':
                                                if (!isset($merchantConfigCcardFitList['jcb'])) {
                                                    doLog(
                                                        $page,
                                                        'NONE',
                                                        'Brand ' . $fit . ' is not configured for this merchant.'
                                                    );
                                                    $errorCodes[] = PAYSYS_NOT_ACTIVATED + FINANCIALINSTITUTION;
                                                    break 2;
                                                }
                                                if (isset($fitIndex['jcb'])) {
                                                    if ('Y' === $allowedCcardFitList[$fitIndex['jcb']]['OPTIONS'][0]) {
                                                        // 2 entries with J/Secure.
                                                        doLog($page, 'NONE', 'Duplicate entry for FIT=JCB.');
                                                        $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                        break 2;
                                                    } else {
                                                        // JCB already added - with SSL only.
                                                        // Replace it with the merchant's default JCB configuration.
                                                        $allowedCcardFitList[$fitIndex['jcb']] = $merchantConfigCcardFitList['jcb'];
                                                    }
                                                }else {
                                                    $allowedCcardFitList[] = $merchantConfigCcardFitList['jcb'];
                                                    $fitIndex['jcb'] = count($allowedCcardFitList) - 1;
                                                }
                                                break;
                                            default:
                                                doLog($page, 'NONE', 'Given FIT not recognized: ' . $fit);
                                                $errorCodes[] = PARAMETER_INVALID + FINANCIALINSTITUTION;
                                                break 2;
                                        }
                                    }
                                    if (!$useMcsc && !$useVbv && !$useAmexSk && !$useDinersClubProtectBuy && !$useDiscoverProtectBuy) {
                                        foreach ($allowedCcardFitList as $ccardFit) {
                                            if ($ccardFit['BRAND'] != 'MC' && $ccardFit['BRAND'] != 'Visa' &&
                                                $ccardFit['BRAND'] != 'Amex' && $ccardFit['BRAND'] != 'Diners' &&
                                                $ccardFit['BRAND'] != 'Discover' && $ccardFit['BRAND'] != 'Maestro'
                                            ) {
                                                //if mcsc and vbv are not used we have to configure each other paymentType as SSL
                                                $allowedCcardFitList[$fitIndex[strtolower($ccardFit['BRAND'])]]['OPTIONS'][0] = 'N'; // 3DS enabled
                                                doLog('DEBUG', 'DEBUG', 'Disabling 3D for Brand ' . $ccardFit['BRAND']);
                                            }
                                        }
                                    }

                                    if ($useMcsc && !$useMC) {
                                        doLog($page, 'NONE', 'FinancialInstitution MCSC has been enabled. MC Fallback is not allowed.');
                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][1] = 'Y';
                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][2] = 'N';
                                        $allowedCcardFitList[$fitIndex['mc']]['OPTIONS'][3] = 'N';
                                    }
                                    if ($useVbv && !$useVisa) {
                                        doLog($page, 'NONE', 'FinancialInstitution VBV has been enabled. Visa Fallback is not allowed');
                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][1] = 'Y';
                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][2] = 'N';
                                        $allowedCcardFitList[$fitIndex['visa']]['OPTIONS'][3] = 'N';
                                    }
                                    if ($useAmexSk && !$useAmex) {
                                        doLog($page, 'NONE', 'FinancialInstitution AmexSafeKey has been enabled. Amex Fallback is not allowed');
                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][1] = 'Y';
                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][2] = 'N';
                                        $allowedCcardFitList[$fitIndex['amex']]['OPTIONS'][3] = 'N';
                                    }
                                    if ($useDinersClubProtectBuy && !$useDiners) {
                                        doLog($page, 'NONE', 'FinancialInstitution Diners Club ProtectBuy has been enabled. Diners Club Fallback is not allowed');
                                        $allowedCcardFitList[$fitIndex['diners']]['OPTIONS'][1] = 'Y';
                                        $allowedCcardFitList[$fitIndex['diners']]['OPTIONS'][2] = 'N';
                                        $allowedCcardFitList[$fitIndex['diners']]['OPTIONS'][3] = 'N';
                                    }

                                    if ($useDiscoverProtectBuy && !$useDiners) {
                                        doLog($page, 'NONE', 'FinancialInstitution Discover ProtectBuy has been enabled. Discover Fallback is not allowed');
                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][1] = 'Y';
                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][2] = 'N';
                                        $allowedCcardFitList[$fitIndex['discover']]['OPTIONS'][3] = 'N';
                                    }

                                    $qpay["merchantInformation"]["paysysConfig"][$pType] = $allowedCcardFitList;

                                } else {
                                    //we can't show an error because that would break backward compatibility (some merchants may give us a fit for ccard)
                                    doLog($page, 'WARN', 'Got FIT=' . $qpay['financialInstitution'] . ' but merchant is not allowed to use for CCARD.');
                                    $qpay['financialInstitution'] = '';
                                }
                            } else {
                                $qpay['financialInstitution'] = '';
                            }
                        } else {
                            $qpay["financialInstitution"] = "";
                        }
                    } else {
                        if ($pType == "SELECT") {
                            // noop
                        } else {
                            if (!PaymentTypeList::isPaymentType($pType)) {
                                $errorCodes[] = PARAMETER_INVALID + PAYMENTTYPE;
                            } else {
                                $errorCodes[] = PAYSYS_NOT_ACTIVATED + PAYMENTTYPE;
                            }
                        }
                    }
                }
            }
        } else {
            $errorCodes[] = PARAMETER_INVALID + SHOPID;
        }
    } else {
        $errorCodes[] = PARAMETER_INVALID + CUSTOMERID;
    }
}

if (isset($pType)) {
    $qpay['financialInstitution'] = getConfiguredFinancialInstitutionForEPS($pType, $qpay);
}

$amountExp10 = -2;
if (!validateIsSet('currency', $qpay)) {
    $errorCodes[] = PARAMETER_MISSING + CURRENCY;
} else {
    if (!validateCurrency($qpay['currency'], $qpay['valuta'], $amountExp10)) {
        $errorCodes[] = PARAMETER_INVALID + CURRENCY;
    } else {
        $qpay["order"]["currency"] = $qpay["currency"];
        $qpay["amountExp10"] = $amountExp10;
    }
}

if (!(isset($qpay["language"]) && trim($qpay["language"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + LANGUAGE;
    $qpay["language"] = "en";
} else {
    if (isset($qpay["merchantInformation"])) {
        // make translations from documented languages to real codes (MID=4049, reversed)
        // http://www.loc.gov/standards/iso639-2/php/code_list.php
        if ($qpay["language"] == "pg") {
            doLog($page, "WARN", "Got deprecated lanugage=pg. Replacing with language=pt.");
            $qpay["language"] = "pt";
        } elseif ($qpay["language"] == "cz") {
            doLog($page, "WARN", "Got deprecated lanugage=cz. Replacing with language=cs.");
            $qpay["language"] = "cz";
        } elseif ($qpay["language"] == "se") {
            doLog($page, "WARN", "Got deprecated lanugage=se. Replacing with language=sv.");
            $qpay["language"] = "sv";
        } elseif ($qpay["language"] == "jp") {
            doLog($page, "WARN", "Got deprecated lanugage=jp. Replacing with language=ja.");
            $qpay["language"] = "ja";
        }


        if (in_array($qpay["language"], $qpay["merchantInformation"]["languages"])) {
            // noop, because language is available and licensed for merchant
        } else {
            if (LanguageList::isLanguage($qpay['language'], true) && $qpay['merchantInformation']['fallbackLanguage'] != 'None') {
                doLog($page, 'WARN',
                    sprintf('Got not activated/not supported language: %s, using fallback: %s',
                        $qpay['language'], strtolower($qpay['merchantInformation']['fallbackLanguage']))
                );
                $qpay['language'] = strtolower($qpay['merchantInformation']['fallbackLanguage']);
            } else {
                doLog($page, 'WARN', 'Got invalid language: ' . $qpay["language"]);
                $errorCodes[] = PARAMETER_INVALID + LANGUAGE;
                $qpay["language"] = "en";
            }
        }
    } else {
        // no merchantinformation set, for this reason we should use en as default language for error output
        // but only, if language is not german
        if (!($qpay["language"] == "de")) {
            $qpay["language"] = "en";
        }
    }
}

// defaults set by merchant config
// can override by sending POST displayBasketData, displayShippingData, displayBillingData
// e.g. merchant config: E, parameter value: N -> don't display
// e.g. merchant config: E, parameter value: Y -> display but not editable
// e.g. merchant config: N, parameter value: Y -> don't display, not allowed by merchant config
// e.g. merchant config: Y, parameter emtpy -> display

$_dbas = isset($qpay['merchantInformation']['displayBasketData']) ? $qpay['merchantInformation']['displayBasketData'] : 'N';
$_dbil = isset($qpay['merchantInformation']['displayBillingData']) ? $qpay['merchantInformation']['displayBillingData'] : 'N';
$_dshi = isset($qpay['merchantInformation']['displayShippingData']) ? $qpay['merchantInformation']['displayShippingData'] : 'N';
$merchantDisplayBasketData   = $merchantConfigPermissions->{yne($_dbas)};
$merchantDisplayBillingData  = $merchantConfigPermissions->{yne($_dbil)};
$merchantDisplayShippingData = $merchantConfigPermissions->{yne($_dshi)};

// check if merchant is allowed to choose to display or edit by merchant settings
$qpay['displayBasketData'] = min($paramDisplayBasketData, $merchantDisplayBasketData);
$qpay['displayShippingData'] = min($paramDisplayShippingData, $merchantDisplayShippingData);
$qpay['displayBillingData'] = min($paramDisplayBillingData, $merchantDisplayBillingData);

if (!(isset($qpay["orderDescription"]) && trim($qpay["orderDescription"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + ORDERDESCRIPTION;
} else {
    if (strlen($qpay["orderDescription"]) > 255) {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + ORDERDESCRIPTION;
    } else {
        $qpay["order"]["orderDescription"] = $qpay["orderDescription"];
    }
}

if (!(isset($qpay["displayText"]) && trim($qpay["displayText"]) != "")) {
    $qpay["displayText"] = (isset($qpay["orderDescription"]) ? $qpay["orderDescription"] : "");
}

if (isset($qpay["customerStatement"])) {
    if (strlen($qpay["customerStatement"]) > 254) {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + CUSTOMERSTATEMENT;
    } else {
        $qpay["order"]["customerStatement"] = $qpay["customerStatement"];
    }
}

if (isset($qpay["orderReference"])) {
    if (strlen($qpay["orderReference"]) > 128) {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + ORDERREFERENCE;
    } else {
        $qpay["order"]["orderReference"] = $qpay["orderReference"];
    }
}

if (!(isset($qpay["successUrl"]) && trim($qpay["successUrl"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + SUCCESSURL;
} else {
    if (isValidUrl($qpay["successUrl"], $reason, true, true) == false) {
        doLog($page, "NONE", "Success-Url invalid: " . $qpay["successUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + SUCCESSURL;

        $qpay["successUrl"] = "";
    }
}

if (!(isset($qpay["failureUrl"]) && trim($qpay["failureUrl"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + FAILUREURL;
    $errorIsRecoverable = false;
} else {
    if (isValidUrl($qpay["failureUrl"], $reason, true, true) == false) {
        doLog($page, "NONE", "Failure-Url invalid: " . $qpay["failureUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + FAILUREURL;
        $errorIsRecoverable = false;

        $qpay["failureUrl"] = "";
    }
}

if (!(isset($qpay["cancelUrl"]) && trim($qpay["cancelUrl"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + CANCELURL;
} else {
    if (isValidUrl($qpay["cancelUrl"], $reason, true, true) == false) {
        doLog($page, "NONE", "Cancel-Url invalid: " . $qpay["cancelUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + CANCELURL;

        $qpay["cancelUrl"] = "";
    }
}

if (!(isset($qpay["serviceUrl"]) && trim($qpay["serviceUrl"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + SERVICEURL;
} else {
    if (strlen($qpay["serviceUrl"]) > 255) {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + SERVICEURL;
    } elseif (isValidUrl($qpay["serviceUrl"], $reason, true, true) == false) {
        doLog($page, "NONE", "Service-Url invalid: " . $qpay["serviceUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + SERVICEURL;
    }
}

if (isset($qpay["confirmUrl"]) && trim($qpay["confirmUrl"]) != "") {
    if (isValidUrl($qpay["confirmUrl"], $reason, $QPAY_TESTSYSTEM_MODE, $QPAY_TESTSYSTEM_MODE) == false) {
        doLog($page, "NONE", "Confirmation-Url invalid: " . $qpay["confirmUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + CONFIRMURL;

        $qpay["confirmUrl"] = ""; // not sending a confirmation to an invalid url
    }
}

// check has to be below confirmUrl validation
if (isset($qpay["pendingUrl"]) && trim($qpay["pendingUrl"]) != "") {
    if (isValidUrl($qpay["pendingUrl"], $reason, true, true) == false) {
        doLog($page, "NONE", "Pending-Url invalid: " . $qpay["pendingUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + PENDINGURL;

        $qpay["pendingUrl"] = "";
    } else {
        // confirmUrl has to be used in combinatin with pendingUrl, if ordernumber is not returned and getorderdetails is not enabled (MID=7269)
        if (!isset($qpay['confirmUrl']) || $qpay['confirmUrl'] == '') {
            if (!($qpay["merchantInformation"]["return"]["pendingOrderNumber"] == "Y") &&
                in_array('GETORDERDETAILS', $qpay['merchantInformation']['toolkit']['commands'])
            ) {
                if (!in_array(PARAMETER_INVALID + CONFIRMURL, $errorCodes)) {
                    doLog($page, "NONE", "Confirmation-Url missing for Pending-Url.");
                    $errorCodes[] = PARAMETER_MISSING + CONFIRMURL;
                }
            }
        }
    }
}


if (isset($qpay["imageUrl"]) && trim($qpay["imageUrl"]) != "") {
    if (isValidUrl($qpay["imageUrl"], $reason, $QPAY_TESTSYSTEM_MODE, $QPAY_TESTSYSTEM_MODE)) {
        // check the filetype, no dynamic image (url with parameters) is allowed
        $isValid = false;
        if (strcasecmp(substr($qpay["imageUrl"], -4), ".gif") == 0) {
            $isValid = true;
        }
        if (strcasecmp(substr($qpay["imageUrl"], -4), ".jpg") == 0) {
            $isValid = true;
        }
        if (strcasecmp(substr($qpay["imageUrl"], -5), ".jpeg") == 0) {
            $isValid = true;
        }
        if (strcasecmp(substr($qpay["imageUrl"], -4), ".png") == 0) {
            $isValid = true;
        }

        if ($isValid) {
            if (isset($qpay["merchantInformation"])) {
                $qpay["merchantImage-Name"] = substr($qpay["imageUrl"], strrpos($qpay["imageUrl"], "/") + 1);

                // if filename exists already within merchant directory, we can skip the image copy for saving ressources
                $merchantImageDirectory = dirname(
                        $_SERVER["SCRIPT_FILENAME"]
                    ) . "/" . $qpay["merchantInformation"]["identifier"] . '/img/';
                if (file_exists($merchantImageDirectory . 'merchantImage_95x65_' . $qpay["merchantImage-Name"]) &&
                    file_exists($merchantImageDirectory . 'merchantImage_200x100_' . $qpay["merchantImage-Name"]) &&
                    file_exists($merchantImageDirectory . 'merchantImage_AsIs_' . $qpay["merchantImage-Name"]) &&
                    $qpay["merchantInformation"]['useCachedMerchantImage'] == 'Y'
                ) {
                    doLog($page, 'NONE', 'Using already existing merchantImage on filesystem.');
                    $qpay['merchantImageAlreadyExists'] = true;
                } else {
                    $qpay["merchantImage-Temp"] = "/tmp/merchantImage-" . $qpay["merchantInformation"]["identifier"] . "_" . rand(
                            1,
                            100000
                        ) . "_" . $qpay["merchantImage-Name"];

                    doLog($page, "NONE", "Trying to copy merchant image from: " . $qpay["imageUrl"]);

                    $aClientConfiguration = array(
                        'confirmationTimeout' => $qpay['merchantInformation']['notificationTimeout'],
                        'confirmationSslVersion' => (
                        isset($qpay['merchantInformation']['notificationSslVersion']) ?
                            $qpay['merchantInformation']['notificationSslVersion'] : 'SSLv3'
                        ),
                    );
                    doLog(
                        $page,
                        'NONE',
                        sprintf(
                            'Using confirmationTimeout \'%s\' and confirmationSslVersion \'%s\' for copy imageUrl call',
                            $aClientConfiguration['confirmationTimeout'],
                            $aClientConfiguration['confirmationSslVersion']
                        )
                    );

                    $sCopyRemoteMerchantImageMessage = '';
                    if (!copyRemoteMerchantImage($qpay["imageUrl"], $qpay["merchantImage-Temp"], $sCopyRemoteMerchantImageMessage, $aClientConfiguration)) {
                        doLog($page, "WARN", "Image-Url invalid: " . $qpay["imageUrl"] . ", Reason: Copy error: " . $sCopyRemoteMerchantImageMessage);
                        $warningMessageForJournal .= "Copy error from ImageUrl: " . $sCopyRemoteMerchantImageMessage;

                        // reset the parameters
                        $qpay["imageUrl"] = "";
                        $qpay["merchantImage-Temp"] = "";
                    }
                }
            }
        } else {
            doLog($page, "NONE", "Image-Url invalid: " . $qpay["imageUrl"] . ", Reason: Wrong file extension.");
            $errorCodes[] = PARAMETER_INVALID + IMAGEURL;
        }
    } else {
        doLog($page, "NONE", "Image-Url invalid: " . $qpay["imageUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + IMAGEURL;
        $qpay["imageUrl"] = "";
    }
}
if (isset($qpay["noScriptInfoUrl"]) && trim($qpay["noScriptInfoUrl"]) != "") {
    if (isValidUrl($qpay["noScriptInfoUrl"], $reason, $QPAY_TESTSYSTEM_MODE, $QPAY_TESTSYSTEM_MODE) == false) {
        doLog($page, "NONE", "Noscript-Url invalid: " . $qpay["noScriptInfoUrl"] . ", Reason: " . $reason);
        $errorCodes[] = PARAMETER_INVALID + NOSCRIPTINFOURL;

        $qpay["noScriptInfoUrl"] = ""; // no redirect to invalid url
    }
}

if (isset($qpay["windowName"]) && trim($qpay["windowName"]) != "") {
    $pattern = "/^[A-Za-z][A-Za-z_0-9]+$/";
    if (!preg_match($pattern, $qpay["windowName"])) {
        doLog($page, "WARN", "WindowName invalid: " . $qpay["windowName"]);
        $warningMessageForJournal .= "WindowName is invalid. ";

        // we store the invalid windowname for later comparison reasons (MID=4907, MID=5776)
        $qpay['invalidWindowName'] = $qpay['windowName'];

        // reset the windowName
        $qpay["windowName"] = "";
    }
}

// if whitelabel partner is set 
if (isset($qpay['whitelabelConfig'])) {
  //doLog($page, $_SERVER["SERVER_NAME"], "Whitelabel: Company name: " . $qpay['whitelabelConfig']['company_name']);
  // override checkout hostname
  if ($qpay['whitelabelConfig']['company_hostname']) {
    // we can reasonably assume a WL partner uses ssl and on standard port
    $CHECKOUT_HTPATH = 'https://' . $qpay['whitelabelConfig']['company_hostname'];
  }
  // override default layout of customerId if a whitelabel layout is set
  if ($qpay['whitelabelConfig']['layout']) {
    $qpay['merchantInformation']['layoutProfile'] = $qpay['whitelabelConfig']['layout'];
    //doLog($page, $_SERVER["SERVER_NAME"], "Whitelabel: Template " . $qpay['whitelabelConfig']['layout']);
  }
  if ($qpay['whitelabelConfig']['stylesheet']) {
    //doLog($page, $_SERVER["SERVER_NAME"], "Whitelabel: Custom stylesheet used");
  }
}

// only, if merchant configuration was found, because default configured settings needed
if (isset($qpay["merchantInformation"])) {
    if (isset($qpay['layout']) && trim($qpay['layout']) != '') {
        if (!isValidLayout($qpay['layout'], $qpay['merchantInformation']['allowedLayouts'])) {
            $errorCodes[] = PARAMETER_INVALID + LAYOUT;
        } elseif ($qpay['layout'] == 'DESKTOP') {
            doLog($page, "NONE", "Default layout defined. No need to change configuration.");
        } else {
            doLog($page, "NONE", sprintf('Using defined layout %s.', $qpay['layout']));
            $qpay['merchantInformation']['layoutProfile'] = $qpay['layout'];
        }
    } else {
        $qpay['layout'] = 'DESKTOP';
    }

    if (!validateIsSet('amount', $qpay)) {
        $errorCodes[] = PARAMETER_MISSING + AMOUNT;
    } else {
        $tmpAmount = $qpay['amount'];
        $isInitial = (isset($qpay['transactionIdentifier']) && strtoupper($qpay['transactionIdentifier']) == "INITIAL"); 

        if (!validateAmount($tmpAmount, $amountExp10, $qpay['merchantInformation']['formats']['amount'], isZeroAmountAllowed(
            $qpay['merchantInformation']['zeroAuth'],
            $qpay['paymentType'],
            $isInitial
        ))
        ) {
            $errorCodes[] = PARAMETER_INVALID + AMOUNT;
        } else {
            $qpay['order']['amount'] = $qpay['amount'];

        }
    }

    if (validateIsSet('amount_net', $qpay)) {
        if (!validateAmount($qpay['amount_net'], $amountExp10, $qpay['merchantInformation']['formats']['amount'])) {
            // should be AMOUNT_NET but that is undefined. to not throw error, AMOUNT is used instead
            $errorCodes[] = PARAMETER_INVALID + AMOUNT;
        } else {
            $qpay['order']['amount_net'] = $qpay['amount_net'];
        }
    }


    $aLayoutProfiles = array(
        'QPAY30' => array('backgroundColor' => 'F5F5F5'),
        'QPAY20' => array('backgroundColor' => 'FFFFFF'),
        'WIRECARD' => array('backgroundColor' => 'FFFFFF'),
        'POWERGAP' => array('backgroundColor' => 'FFFFFF'),
        'MOBILE' => array('backgroundColor' => 'FFFFFF'),
        'QPAY314' => array('backgroundColor' => 'FFFFFF'),
        'QMORE' => array('backgroundColor' => 'FFFFFF'),
        'TABLET' => array('backgroundColor' => 'FFFFFF'),
        'SMARTPHONE' => array('backgroundColor' => 'FFFFFF'),
        'RESPONSIVE' => array('backgroundColor' => 'FFFFFF'),
        'SEAMLESS-RESPONSIVE' => array('backgroundColor' => 'FFFFFF'),
    );

    if (!isset($qpay["backgroundColor"]) || trim($qpay["backgroundColor"]) == "") {
        $sLayoutProfile = $qpay["merchantInformation"]["layoutProfile"];
        // default value depends on layout-profile (MID=3709)
        if (array_key_exists($qpay["merchantInformation"]["layoutProfile"], $aLayoutProfiles)) {
            $sBackgroundColor = $aLayoutProfiles[$qpay["merchantInformation"]["layoutProfile"]]['backgroundColor'];
            doLog("DEBUG", "DEBUG", sprintf('Using background color %s from layout profile %s', $sBackgroundColor, $sLayoutProfile));
            $qpay['backgroundColor'] = $sBackgroundColor;
        } else {
            doLog($page, "WARN", "Unknown layout profile: " . $qpay["merchantInformation"]["layoutProfile"]);
            $qpay["backgroundColor"] = "FFFFFF";
        }
    } else {
        //$pattern = "/^([A-Fa-f0-9]{6}|black|maroon|green|olive|navy|purple|teal|silver|gray|red|lime|yellow|blue|fuchsia|aqua|white)$/";
        $pattern = "/^([A-Fa-f0-9]{6})$/";
        if (!preg_match($pattern, $qpay["backgroundColor"])) {
            doLog($page, "WARN", "BackgroundColor invalid: " . $qpay["backgroundColor"]);
            $errorCodes[] = PARAMETER_INVALID + BACKGROUNDCOLOR;
            $qpay["backgroundColor"] = "F5F5F5";
        }
    }

    // read validate and allow control-parameters (MID=3685)
    if (isset($qpay["autoDeposit"]) && $qpay["autoDeposit"] != "") {
        if (strcasecmp(trim($qpay["autoDeposit"]), "YES") == 0 || strcasecmp(trim($qpay["autoDeposit"]), "ON") == 0 || strcasecmp(trim($qpay["autoDeposit"]), "TRUE") == 0) {
            if ($qpay["merchantInformation"]["control"]["autoDeposit"] == 'Y') {
                // allowing autoDeposit, using boolean
                $qpay["autoDeposit"] = true;
            } else {
                doLog($page, "WARN", "Merchant sent positive autoDeposit-Flag but is not allowed to use it.");
                $errorCodes[] = PARAMETER_NOT_ALLOWED + AUTODEPOSIT;
                $qpay["autoDeposit"] = false;
            }
        } elseif (strcasecmp(trim($qpay["autoDeposit"]), "NO") == 0 || strcasecmp(trim($qpay["autoDeposit"]), "OFF") == 0 || strcasecmp(trim($qpay["autoDeposit"]), "FALSE") == 0) {
            // not wanted, but ok from content meaning
            $qpay["autoDeposit"] = false;
        } else {
            doLog($page, "WARN", "Got invalid value for autoDeposit: " . $qpay["autoDeposit"]);
            $errorCodes[] = PARAMETER_INVALID + AUTODEPOSIT;
            $qpay["autoDeposit"] = false;
        }
    }

    if (validateIsSet('riskSuppress', $qpay)) {
        if (validateBooleanValue($qpay['riskSuppress'])) {
            if ($qpay["merchantInformation"]["control"]["riskSuppress"] == 'Y') {
                // allowing autoDeposit, using boolean
                $qpay['riskSuppress'] = getBooleanValue($qpay['riskSuppress']);
            } else {
                doLog($page, "WARN", "Merchant sent positive riskSuppress-Flag but is not allowed to use it.");
                $errorCodes[] = PARAMETER_NOT_ALLOWED + RISK_SUPPRESS;
                $qpay["riskSuppress"] = false;
            }
        } else {
            doLog($page, "WARN", 'Got invalid value for riskSuppress: ' . $qpay['riskSuppress']);
            $errorCodes[] = PARAMETER_INVALID + RISK_SUPPRESS;
            $qpay['riskSuppress'] = false;
        }
    } elseif (array_key_exists('riskSuppress', $qpay)) {
        unset($qpay['riskSuppress']);
    }

    if (validateIsSet('riskConfigAlias', $qpay)) {
        $pattern = "/^([A-Za-z-0-9]{1,16})$/";
        if (preg_match($pattern, $qpay['riskConfigAlias'])) {
            if ($qpay["merchantInformation"]["control"]["riskConfigAlias"] == 'N') {
                doLog($page, "WARN", "Merchant sent riskConfigAlias-Flag but is not allowed to use it.");
                $errorCodes[] = PARAMETER_NOT_ALLOWED + RISK_CONFIG_ALIAS;
                $qpay["riskConfigAlias"] = false;
            }
        } else {
            doLog($page, "WARN", 'Got invalid length for riskConfigAlias: ' . $qpay['riskConfigAlias']);
            $errorCodes[] = PARAMETER_INVALID_LENGTH + RISK_CONFIG_ALIAS;
            $qpay['riskConfigAlias'] = false;
        }
    } elseif (array_key_exists('riskConfigAlias', $qpay)) {
        unset($qpay['riskConfigAlias']);
    }

    if (isset($qpay["merchantInformation"]["control"]["splitCardholder"])) {
        if ($qpay["merchantInformation"]["control"]["splitCardholder"] == 'Y') {
            $qpay['splitCardholder'] = true;
        } else {
            $qpay["splitCardholder"] = false;
        }
    } elseif (array_key_exists('splitCardholder', $qpay)) {
        unset($qpay['splitCardholder']);
    }

    if( isset($qpay["merchantInformation"]["control"]["hidePaymentOptions"]) && 
        $qpay["merchantInformation"]["control"]["hidePaymentOptions"] == 'Y'){

        if(array_key_exists("CRYPTO", $qpay['merchantInformation']['paysysConfig'])){
            // check for salamantex, formatting it this way to make costumisations for possible additional
            // crypto providers in the future easier
            $salamantex_index = array_search("Salamantex", array_column($qpay['merchantInformation']['paysysConfig']['CRYPTO'], 'BRAND'));

            if(!$salamantex_index){
                if (!(isset($qpay["pendingUrl"]) && trim($qpay["pendingUrl"]) != "")) {
                    unset( $qpay['merchantInformation']['paysysConfig']['CRYPTO'][$salamantex_index]);
                }
            }

            // remove crypto array if its empty
            if( empty($qpay['merchantInformation']['paysysConfig']['CRYPTO']) ){
                unset( $qpay['merchantInformation']['paysysConfig']['CRYPTO']);
            }
        }

        if(array_key_exists("RIVERTY", $qpay['merchantInformation']['paysysConfig'])){
            $paramIsMissing = false;
            if ( !(isset($qpay["basketItems"]) && trim($qpay["basketItems"]) != "") &&
                !(isset($qpay['basketItem'][1]['articleNumber']) &&  trim($qpay['basketItem'][1]['articleNumber']) != "")) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingFirstName"]) && trim($qpay["consumerBillingFirstName"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingLastName"]) && trim($qpay["consumerBillingLastName"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingAddress1"]) && trim($qpay["consumerBillingAddress1"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingZipCode"]) && trim($qpay["consumerBillingZipCode"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingCity"]) && trim($qpay["consumerBillingCity"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerBillingCountry"]) && trim($qpay["consumerBillingCountry"]) != "") ) {
                $paramIsMissing = true;
            } elseif ( !(isset($qpay["consumerEmail"]) && trim($qpay["consumerEmail"]) != "")  ) {
                $paramIsMissing = true;
            }

            if($paramIsMissing){
                unset( $qpay['merchantInformation']['paysysConfig']['RIVERTY']);
            }
        }

    }

    $subMerchant = false;
    if ($qpay['merchantInformation']['isPaymentFacilitator'] == 'Y') { //if merchant is a configured facilitator we need the fields
        $subMerchant = array_key_exists('submerchant', $qpay) ? $qpay['submerchant'] : array();
        $subMerchant['ispaymentfacilitator'] = true;
    } elseif (array_key_exists('submerchant', $qpay)) { //if one field is set we expect all parameters to be set
        $subMerchant = $qpay['submerchant'];
    }

    if ($subMerchant !== false) {
        $subMerchantResult = validateSubMerchantParameters($subMerchant);
        if ($subMerchantResult['status'] === true) {
            $qpay['submerchant'] = $subMerchantResult['fields'];
        } else {
            $errorCodes = array_merge($errorCodes, $subMerchantResult['errors']);
        }
    }

    if (isset($qpay["maxRetries"]) && $qpay["maxRetries"] != "") {
        if ($qpay["merchantInformation"]["control"]["maxRetries"] == 'Y') {
            $pattern = "/^-?\d+$/";
            if (!preg_match($pattern, $qpay["maxRetries"])) {
                doLog($page, "NONE", "MaxRetries invalid: " . $qpay["maxRetries"]);
                $errorCodes[] = PARAMETER_INVALID + MAXRETRIES;
                $qpay["maxRetries"] = "";
            }
        } else {
            doLog($page, "WARN", "Merchant sent maxRetries but is not allowed to use it.");
            $errorCodes[] = PARAMETER_NOT_ALLOWED + MAXRETRIES;
            $qpay["maxRetries"] = "";
        }
    } else {
        // using merchants default setting
        $qpay["maxRetries"] = $qpay["merchantInformation"]["maxRetries"];
    }


    if (isset($qpay['paymentTypeSortOrder']) && trim($qpay['paymentTypeSortOrder']) != '') {
        $sPaymentTypes = str_replace('SEPA-DD', 'ELV', strtoupper($qpay['paymentTypeSortOrder']));
        //EPS,BA-CA,RACON,CCARD,IDL,PAYPAL, GOOGLEPAY...
        $paymentTypeSortOrder = explode(',', $sPaymentTypes);
        $paymentTypeCounter = 0;
        $epsCounter = 1;
        $idlCounter = 1;
        require_once 'inc/PaymentTypeList.class.php';
        /*
             * e.G.
             * EPS/BA-CA/CCARD
             * ['displayOptions']['paymentTypes']['EPS']['sortOrder'] = 1
             * ['displayOptions']['paymentTypes']['EPS']['financialInstitutions']['BA-CA']['sortOrder'] = 1
             * ['displayOptions']['paymentTypes']['CCARD']['sortOrder'] = 2
            */
        foreach ($paymentTypeSortOrder as $sortOrderEntry) {
            //capitalize everything and trim whitespaces
            $sortOrderEntry = strtoupper(trim($sortOrderEntry));
            if (PaymentTypeList::isPaymentType($sortOrderEntry) && $sortOrderEntry != PaymentTypeList::SELECT && $sortOrderEntry != PaymentTypeList::CCARD_MOTO) {
                if (isset($qpay['displayOptions']['paymentTypes'][$sortOrderEntry]['sortOrder'])) {
                    doLog($page, 'NONE', sprintf('PaymentType %s has been defined twice in paymentTypeSortOrder.', $sortOrderEntry));
                    $errorCodes[] = PARAMETER_INVALID + PAYMENTTYPESORTORDER;
                    //error is already stored. We can continue after the foreach
                    break;
                } else {
                    $qpay['displayOptions']['paymentTypes'][$sortOrderEntry]['sortOrder'] = $paymentTypeCounter;
                    $paymentTypeCounter++;
                }
            } elseif (PaymentTypeList::isFinancialInstitution($sortOrderEntry, PaymentTypeList::EPS)) {
                if (isset($qpay['displayOptions']['paymentTypes'][PaymentTypeList::EPS]['financialInstitutions'][$sortOrderEntry]['sortOrder'])) {
                    doLog($page, 'NONE', sprintf('FinancialInstitution %s has been defined twice in paymentTypeSortOrder.', $sortOrderEntry));
                    $errorCodes[] = PARAMETER_INVALID + PAYMENTTYPESORTORDER;
                    //error is already stored. We can continue after the foreach
                    break;
                } else {
                    $qpay['displayOptions']['paymentTypes'][PaymentTypeList::EPS]['financialInstitutions'][$sortOrderEntry]['sortOrder'] = $epsCounter;
                    $epsCounter++;
                }
            } elseif (PaymentTypeList::isFinancialInstitution($sortOrderEntry, PaymentTypeList::IDL)) {
                if (isset($qpay['displayOptions']['paymentTypes'][PaymentTypeList::IDL]['financialInstitutions'][$sortOrderEntry]['sortOrder'])) {
                    doLog($page, 'NONE', sprintf('FinancialInstitution %s has been defined twice in paymentTypeSortOrder.', $sortOrderEntry));
                    $errorCodes[] = PARAMETER_INVALID + PAYMENTTYPESORTORDER;
                    //error is already stored. We can continue after the foreach
                    break;
                } else {
                    $qpay['displayOptions']['paymentTypes'][PaymentTypeList::IDL]['financialInstitutions'][$sortOrderEntry]['sortOrder'] = $idlCounter;
                    $idlCounter++;
                }
            } elseif ($sortOrderEntry == '') {
                //ignore empty entry
            } else {
                doLog($page, 'NONE', sprintf('Unknown paymentType/financialInstitution %s in paymentTypeSortOrder', $sortOrderEntry));
                $errorCodes[] = PARAMETER_INVALID + PAYMENTTYPESORTORDER;
                break;
            }
        }
    }

    if (isset($qpay['confirmMail']) && trim($qpay['confirmMail']) != '') {
        if ($qpay['merchantInformation']['allowNotificationEmail'] != 'Y') {
            doLog($page, "WARN", "Merchant sent confirmMail but is not allowed to use it.");
            $errorCodes[] = PARAMETER_NOT_ALLOWED + CONFIRMMAIL;
            $qpay["confirmMail"] = "";
        } elseif (isValidMailAddress($qpay['confirmMail'], $reason) == false) {
            doLog($page, "NONE", "Confirmation-Mail invalid: " . $qpay["confirmMail"] . ", Reason: " . $reason);
            $errorCodes[] = PARAMETER_INVALID + CONFIRMMAIL;

            $qpay['confirmMail'] = ''; //not sending a confirmation to an invalid mail
        }
    }

    if (count($recipient) !== 0) {
        $result = validateRecipientParameters($recipient);

        if ($result['status']) {
            $qpay['order']['recipient'] = $result['fields'];
        } else {
            $errorCodes = array_merge($errorCodes, $result['errors']);
        }
    }

    if (count($basket) !== 0) {
        $options = array();

        if (!array_key_exists('amount', $basket) && !array_key_exists('currency', $basket)) {
            $options['currency'] = array('name' => $qpay['currency'], 'exponent' => 0, 'valuta' => null);
            validateCurrency($options['currency']['name'], $options['currency']['valuta'], $options['currency']['exponent']);
        }

        $result = validateBasketParameters($basket, $options);

        if ($result['status']) {
            $qpay['order']['basket'] = $result['fields'];
        } else {
            $errorCodes = array_merge($errorCodes, $result['errors']);
        }
    }

    if (count($globalParameter) !== 0) {
        $result = validateGlobalParameters($globalParameter);

        if ($result['status']) {
            $qpay = array_merge($qpay, mapGlobalDataFromModule($result['fields']));
        } else {
            $errorCodes = array_merge($errorCodes, $result['errors']);
        }
    }
}

if (count($consumer) !== 0) {

  // $result = validateConsumerParameters($consumer);
  // html_var_dump($result);
  // html_var_dump($consumer);
  // echo "\n-------------------------------------\n";
  // unset($consumer["shippingaddress"]);
  // $consumer["billingaddress"]["firstname"] = 'sdkcrhasvnurvointhoergtoergoseztcoertoenz7towrtzcower87tvzwr0t7cmzowrtzwer8tcnzw0r8t7zcwe0r7tzwer0nc7twe0ctzrtvwejtoc7qwzt7wmzt0w7ctzwrtozwotwzectn7owt7nwrmto7';
    $result = validateConsumerParameters($consumer);
  //  html_var_dump($result);
  //  html_var_dump($consumer);

    if ($result['status']) {
        if (!array_key_exists('consumerInformation', $qpay)) {
            $qpay['consumerInformation'] = array();
        }
        $qpay['consumerInformation'] = array_merge($qpay['consumerInformation'], mapConsumerDataFromModule($result['fields']));
    } else {
        $errorCodes = array_merge($errorCodes, $result['errors']);
    }
}

if (validateIsSet('companyName', $qpay)) {
    if (validateStringLength(0, 100, $qpay['companyName'])) {
        $qpay['consumerInformation']['company']['name'] = $qpay['companyName'];
    } else {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + COMPANY_NAME;
    }
}

if (validateIsSet('companyVatId', $qpay)) {
    if (validateStringLength(0, 50, $qpay['companyVatId'])) {
        $qpay['consumerInformation']['company']['vatId'] = $qpay['companyVatId'];
    } else {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + COMPANY_VAT_ID;
    }
}

if (validateIsSet('companyTradeRegistryNumber', $qpay)) {
    if (validateStringLength(0, 50, $qpay['companyTradeRegistryNumber'])) {
        $qpay['consumerInformation']['company']['tradeRegistryNumber'] = $qpay['companyTradeRegistryNumber'];
    } else {
        $errorCodes[] = PARAMETER_INVALID_LENGTH + COMPANY_TRADE_REGISTRY_NUMBER;
    }
}

if (validateIsSet('companyRegisterKey', $qpay)) {
    if (validateStringLength(0, 50, $qpay['companyRegisterKey'])) {
        $qpay['consumerInformation']['company']['registerKey'] = $qpay['companyRegisterKey'];
    }
}

if (!(isset($qpay["requestFingerprint"]) && trim($qpay["requestFingerprint"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + FINGERPRINT;
}

if (!(isset($qpay["requestFingerprintOrder"]) && trim($qpay["requestFingerprintOrder"]) != "")) {
    $errorCodes[] = PARAMETER_MISSING + REQUESTFINGERPRINTORDER;
}

// we have to parse and store a possible given plugin version
if (isset($qpay["pluginVersion"]) && $qpay["pluginVersion"] != "") {
    // base64(Shopname; Shopversion; sonstige Abhängigkeiten an Bibliotheken mit Versionsnummern durch Beistriche getrennt; Pluginname; Pluginversion);
    $pluginVersionContainer = base64_decode($qpay["pluginVersion"]);
    if ($pluginVersionContainer != false) {
        $pluginVersionContents = explode(';', $pluginVersionContainer);
        if (count($pluginVersionContents) == 5) {
            store_PROD_qpay_PluginVersion($qpay["customerId"], $qpay["shopId"], $pluginVersionContents);
        } else {
            doLog($page, "WARN", "Invalid content for pluginVersion: " . $pluginVersionContainer);
        }
    } else {
        doLog($page, "WARN", "Unable to decode pluginVersion: " . $qpay["pluginVersion"]);
    }
}


// check if orderNumber-preselection is allowed:
if (isset($qpay["orderNumber"])) {
    if (isset($qpay["maxRetries"]) && (int)$qpay["maxRetries"] == 0) {
        $pattern = "/^\d{1,9}$/";
        if (!preg_match($pattern, $qpay["orderNumber"])) {
            doLog($page, "NONE", "OrderNumber invalid: " . $qpay["orderNumber"]);
            $errorCodes[] = PARAMETER_INVALID + ORDERNUMBER;
            $qpay["orderNumber"] = "";
        } else {
            $mandatoryFields["ORDERNUMBER"] = true;
        }
    } else {
        $reservedWordsUsed[] = "ORDERNUMBER";
    }
}

if (isset($qpay['sourceOrderNumber']) && $qpay['sourceOrderNumber'] !== '') {
    $pattern = "/^\d{1,9}$/";
    if ($qpay['paymentType'] !== 'CCARD') {
        doLog($page, "NONE", "Parameter sourceOrderNumber is not allowed for payment method: " . $qpay['paymentType']);
        $errorCodes[] = PARAMETER_NOT_ALLOWED + SOURCEORDERNUMBER;
    } else if (!preg_match($pattern, $qpay['sourceOrderNumber'])) {
        doLog($page, "NONE", "SourceOrderNumber invalid: " . $qpay['sourceOrderNumber']);
        $errorCodes[] = PARAMETER_INVALID + SOURCEORDERNUMBER;
    } else {
        doLog($page, "NONE", 'Using sourceOrderNumber: ' . $qpay['sourceOrderNumber'] . ' and therefor setting maxRetries to 0');
        $qpay['order']['sourceOrderNumber'] = $qpay['sourceOrderNumber'];
        $mandatoryFields['SOURCEORDERNUMBER'] = true;
        $qpay['maxRetries'] = 0;
    }
}

if (count($reservedWordsUsed) > 0) {
    $errorCodes[] = PARAMETER_NOT_ALLOWED;
    doLog($page, "NONE", "Reserved parameters used for initiation.");
    $errorIsRecoverable = false;
}

// if already a parameter is missing or with wrong format, we have to skip the ordernumber creation
if (count($errorCodes) == 0) {
    if (isset($qpay["orderNumber"]) && trim($qpay["orderNumber"]) != "") {
        // orderNumber was given, we have to check if it is already used for this merchant
        $orderNumber = $qpay["orderNumber"];
        $status = createOrder($orderNumber, $message, $paySysMessage, $errorCode);

        if ($status == 0) {
            if ($orderNumber != $qpay["orderNumber"]) {
                // orderNumber was overwritten by QTILL within createOrder-request because
                // suggested number already in use
                $errorCodes[] = PARAMETER_DUPLICATED + ORDERNUMBER;
            } else {
                $qpay["order"]["orderNumber"] = $orderNumber;
                doLog($page, 'NONE', 'Validated merchant given orderNumber (#' . $qpay["order"]["orderNumber"] . ')');
            }
        } else {
            $errorCodes[] = $errorCode;
            $paySysMessages[$errorCode] = $paySysMessage;
            doLog($page, 'NONE', 'Unable to validate merchant given orderNumber (#' . $orderNumber . '): ' . $paySysMessage . ' [ErrorCode=' . $errorCode . ']');
        }
    } else {
        // we have to create an orderNumber
        $status = createOrder($orderNumber, $message, $paySysMessage, $errorCode);
        if ($status == 0) {
            $qpay["order"]["orderNumber"] = $orderNumber;
            doLog($page, "NONE", 'Generated orderNumber (#' . $qpay["order"]["orderNumber"] . ')');
        } else {
            doLog($page, "NONE", "Unable to generate orderNumber: " . $paySysMessage . " [ErrorCode=" . $errorCode . "]");
            $errorCodes[] = PAYSYS_PENDING;
        }
    }

    // we have to decode to key to know wheter a demo-configuration or not
    // don't forget to check for error-results (MID=3620)
    $keyData = "";
    $status = decodeKey($keyData, $message, $paySysMessage, $errorCode);
    if ($status == 0) {
        $qpay["merchantInformation"]["keyFlags"]["Payment"] = getURLParameter($keyData, "KEYFLAG_PAYMENT");
        $qpay["merchantInformation"]["keyFlags"]["MerchantNumber"] = getURLParameter($keyData, "KEYNUM_MERCHANTNUMBER");
        $qpay["merchantInformation"]["keyFlags"]["MerchantName"] = getURLParameter($keyData, "KEYSTR_USERID");
        if (getURLParameter($keyData, "KEYNUM_ROLEID") == 1) {
            $iSubmerchantId = getURLParameter($keyData, 'KEYNUM_SUBMERCHID');
        } else {
            $iSubmerchantId = -1;
        }
        $qpay["merchantInformation"]["keyFlags"]["SubmerchantId"] = $iSubmerchantId;
    } else {
        doLog($page, "NONE", "Unable to decode merchantKey: " . $paySysMessage . "[ErrorCode=" . $errorCode . "]");
        $errorCodes[] = PAYSYS_PENDING;
    }

    if (array_key_exists('TRUSTPAY', $qpay['merchantInformation']['paysysConfig']) &&
        array_key_exists('showBankSelection', $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'][0]['DISPLAY_OPTIONS']) &&
        $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'][0]['DISPLAY_OPTIONS']['showBankSelection'] == true
    ) {
        $aCountries = array_key_exists('countryWhitelist', $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'][0]['DISPLAY_OPTIONS']) ? $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'][0]['DISPLAY_OPTIONS']['countryWhitelist'] : array();
        $trustPayBanks = addTrustPayBanks($qpay["customerId"], $qpay["shopId"], $aCountries, $qpay["order"]["currency"], $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'][0]
        );
        if (count($trustPayBanks)) {
            $qpay['merchantInformation']['paysysConfig']['TRUSTPAY'] = $trustPayBanks;
        } elseif ($trustPayBanks === false) {
            if ('TRUSTPAY' == $pType) {
                doLog($page, "NONE", 'Unable to retrieve TRUSTPAY BankList. Can\'t continue');
                $errorCodes[] = PAYSYS_PENDING;
            } else {
                doLog($page, SID, 'Unable to retrieve TRUSTPAY BankList.');
            }
        }
    }

    if (array_key_exists('GOOGLEPAY', $qpay['merchantInformation']['paysysConfig'] ) && count($qpay['allowedCardNetworks']) > 0){
        //filter for accepted cards with preg_match_all
        $pattern = '/VISA|AMEX|JCB|MASTERCARD|INTERAC|DISCOVER/';
        preg_match_all($pattern,strtoupper($qpay['allowedCardNetworks']),$filtertCardNetworks);
        if(count($filtertCardNetworks) > 0)
            $qpay['merchantInformation']['paysysConfig']['GOOGLEPAY'][0]['PAYMENT_OPTIONS']['Cardnetworks'] = $filtertCardNetworks[0];//explode (",", $qpay['allowedCardNetworks']);
    }

}

// if already a parameter is missing or with wrong format, we can skip the fingerprint calculation
if (count($errorCodes) == 0 && isset($qpay["merchantInformation"])) {
    $sPattern = "/^([A-Za-z0-9]|[\\+|\\-|\\?|\\/|\\(|\\)|\\:|\\.|\\'|,]){1,35}$/";
    if (isset($qpay['mandateId'])) {
        if (preg_match($sPattern, $qpay['mandateId'])) {
            if (isset($qpay['mandateSignatureDate'])) {
                $mDateTime = DateTime::createFromFormat("d.m.Y", $qpay['mandateSignatureDate']);
                $aDateTimeErrors = DateTime::getLastErrors();
                if ($aDateTimeErrors['warning_count'] || $aDateTimeErrors['error_count']) {
                    doLog($page, "WARN", "Given Mandate Signature Date is not in right format. Received: {$qpay['mandateSignatureDate']}, should be in format: d.m.Y");
                    $errorCodes[] = PARAMETER_INVALID_FORMAT + MANDATE_SIGNATURE_DATE;
                } else {
                    //mandateInformation given.. save to order. MID: 8840
                    $qpay['order']['mandateId'] = $qpay['mandateId'];
                    $qpay['order']['mandateSignatureDate'] = $qpay['mandateSignatureDate'];
                }
            } else {
                doLog($page, "WARN", "MandateId given but no Mandate Signature Date");
                $errorCodes[] = PARAMETER_MISSING + MANDATE_SIGNATURE_DATE;
            }
        } else {
            doLog($page, "WARN", "Given mandateId is invalid format. MandateId = {$qpay['mandateId']}");
            $errorCodes[] = PARAMETER_INVALID + MANDATE_ID;
        }
    }

    if (isset($qpay['creditorId'])) {
        if (!preg_match($sPattern, $qpay['creditorId'])) {
            doLog($page, "WARN", "Given creditorId is invalid format. Given creditorId = {$qpay['creditorId']}");
            $errorCodes[] = PARAMETER_INVALID_FORMAT + CREDITOR_ID;
        } else {
            $qpay['order']['creditorId'] = $qpay['creditorId'];
            doLog($page, "NONE", "Using creditorId = {$qpay['creditorId']}");
        }
    }

    if (isset($qpay['dueDate'])) {
        $mDateTime = DateTime::createFromFormat("d.m.Y", $qpay['dueDate']);
        $aDateTimeErrors = DateTime::getLastErrors();
        if ($aDateTimeErrors['warning_count'] || $aDateTimeErrors['error_count']) {
            doLog($page, "WARN", "Given Due Date is not in right format. Received: {$qpay['dueDate']}, should be in format: d.m.Y");
            $errorCodes[] = PARAMETER_INVALID_FORMAT + DUE_DATE;
        } else {
            $qpay['order']['dueDate'] = $qpay['dueDate'];
            doLog($page, "NONE", "Using dueDate = {$qpay['dueDate']}");
        }
    }

    if (isset($qpay['transactionIdentifier']) && trim($qpay['transactionIdentifier']) != '') {
        if (in_array(strtoupper($qpay['transactionIdentifier']), array('SINGLE', 'INITIAL'))) {
            doLog($page, "NONE", sprintf("Using transactionIdentifier = %s", $qpay['transactionIdentifier']));
            $qpay['order']['transactionIdentifier'] = $qpay['transactionIdentifier'];
        } else {
            doLog($page, "WARN", sprintf("Unknown transactionIdentifier = %s", $qpay['transactionIdentifier']));
            $errorCodes[] = PARAMETER_INVALID + TRANSACTION_IDENTIFIER;
        }
    }

    $qpay['order']['noShipping'] = false;
    if (array_key_exists('shippingProfile', $qpay) && trim($qpay['shippingProfile']) !== '') {
        if (strtoupper($qpay['shippingProfile']) === 'NO_SHIPPING') {
            doLog($page, 'NONE', 'Using NO_SHIPPING option.');
            $qpay['order']['noShipping'] = true;
        } else {
            $shippingProfileService = Zf2Singleton::getService('WCAPI\\MasterPass\\ShippingProfileService');
            $shippingProfileService->setAuthenticationCredentials($qpay["merchantInformation"]["merchantId"], md5($qpay["merchantInformation"]["merchantSecret"]));
            $shippingProfileCollection = Zf2Singleton::getService('WCAPI\\MasterPass\\ShippingProfileCollection');
            try {
                $shippingProfileCollection = $shippingProfileService->getList($shippingProfileCollection);
                if (!$shippingProfileCollection->hasItem($qpay['shippingProfile'])) {
                    $errorCodes[] = PARAMETER_INVALID + SHIPPING_PROFILE;
                } else {
                    $qpay['order']['shippingProfile'] = $qpay['shippingProfile'];
                    doLog($page, 'NONE', sprintf('Using shippingProfile %s.', $qpay['shippingProfile']));
                }
            } catch (WirecardCheckoutApiClient\Exception\ExceptionInterface $e) {
                if ($e->getCode() === 401) {
                    $errorCodes[] = PARAMETER_NOT_ALLOWED + SHIPPING_PROFILE;
                } else {
                    $errorCodes[] = PAYSYS_COMMUNICATION_WITH_FINANCIAL_SERVICE_PROVIDER_FAILED;
                    doLog($page, 'WARN', sprintf('A communication error with the ReST-API occurred: %s (%s)', $e->getCode(), $e->getMessage()));
                }
            }
        }
    }

    // hand over information about mandatory fields
    $qpay['mandatoryFields'] = $mandatoryFields;

    if (trim($qpay["merchantInformation"]["merchantSecret"]) == "") {
        doLog($page, "WARN", "Found empty SECRET in configuration");
        $errorCodes[] = PAYSYS_INVALID_MERCHANT_CONFIG;
    } else {
        // now we can validate the requestfingerprint. the calculation of the responsefingerprint
        // is done in the last page in qpay (before confirmation)
        $str4Fingerprint = "";
        $str4XHMTLFingerprint = "";

        $orderedKeys = explode(",", $qpay["requestFingerprintOrder"]);
        for ($i = 0; $i < count($orderedKeys); $i++) {
            $key = $orderedKeys[$i];
            if (trim($key) == "") {
                // empty value in rfpo-definitions, just ignore
                doLog($page, "WARNING", "Ignoring empty key in requestFingerprintOrder.");
                $warningMessageForJournal .= "Empty key in RequestFingerprintOrder. ";
            } else {
                unset($mandatoryFields[strtoupper($key)]);

                if (strcasecmp($key, "SECRET") == 0) {
                    // the secret is not sent within the request.
                    $str4Fingerprint .= $qpay["merchantInformation"]["merchantSecret"];
                    $str4XHMTLFingerprint .= $qpay["merchantInformation"]["merchantSecret"];
                }
                elseif (strcasecmp($key, "SUCCESSURL") == 0) {
                    $value = $_POST[$key];
                    if (strpos($value, "https://service.salzburg.gv.at/landversand/Landversand.sf") !== false) {
                        doLog($page, "WARNING", "Replacing returnUrl for Land-Salzburg.");
                        $value = str_replace("https://service.salzburg.gv.at/landversand/Landversand.sf", "http://landversand.land-sbg.gv.at/epages/Landversand.sf", $value);
                    }

                    $str4Fingerprint .= $value;
                    $value = str_replace("&", "&amp;", $value);
                    $str4XHMTLFingerprint .= $value;
                } else {
                    if (isset($_POST[$key])) {
                        $value = $_POST[$key];

                        // removed get_magic_quotes_gpc invalid as of PHP 8. returns false since 5.4
                        // kept in comment because, well you never know which abomination of PHP this might
                        // run on in the future...
                        //
                        // removing added magic quotes
                        // if (get_magic_quotes_gpc() && strpos($value, "\\") !== false) {
                        //     $value = stripslashes($value);
                        // }

                        $str4Fingerprint .= $value;
                        if (strlen($key) > 3 && strcasecmp(substr($key, -3), "URL") == 0) {
                            $value = str_replace("&", "&amp;", $value);
                        }
                        $str4XHMTLFingerprint .= $value;
                    } else {
                        // a parameter listed in rfporder is not given in request
                        $optionalFields[] = $key;
                    }
                }
            }
        }

        if (count($optionalFields) > 0) {
            $missingParameters = "";
            foreach ($optionalFields as $key => $value) {
                $missingParameters .= $value . " ";
            }

            doLog($page, "NONE", "Missing optional parameter in Fingerprint: " . $missingParameters);
            $errorCodes[] = PARAMETER_MISSING_USERDEFINED;
        }

        if (count($mandatoryFields) > 0) {
            $missingParameters = "";
            foreach ($mandatoryFields as $key => $value) {
                $missingParameters .= $key . " ";
            }

            doLog($page, "NONE", "Missing mandatory parameter in Fingerprint: " . $missingParameters);
            $errorCodes[] = PARAMETER_INVALID + REQUESTFINGERPRINTORDER;
        }

        // we support different hash algorithm (MID=6446)
        // supported algorithms within php: http://de.php.net/manual/en/function.hash-algos.php
        $hashAlgorithm = 'unknown';
        if ($qpay['merchantInformation']['hashAlgorithm'] == 'MD5') {
            $hashAlgorithm = 'md5';
        } elseif ($qpay['merchantInformation']['hashAlgorithm'] == 'SHA512') {
            $hashAlgorithm = 'sha512';
        } elseif ($qpay['merchantInformation']['hashAlgorithm'] == 'MERCHANT') {
            if (strlen($qpay['requestFingerprint']) == 128) {
                $hashAlgorithm = 'sha512';
            } elseif (strlen($qpay['requestFingerprint']) == 32) {
                $hashAlgorithm = 'md5';
            }
        }

        $hmac_upscaling = false;
        // suppress warnings if unsupported hash algorithm is used. string-comparison will fail therefore
        if ($qpay['merchantInformation']['hashAlgorithm'] === 'HMAC_SHA512') {
            $hashAlgorithm = 'hmac_sha512';
            $calcFingerprint = @hash_hmac('sha512', $str4Fingerprint, $qpay['merchantInformation']['merchantSecret']);
            $calcXHTMLFingerprint = @hash_hmac('sha512', $str4XHMTLFingerprint, $qpay['merchantInformation']['merchantSecret']);
        } else {
            $calcFingerprint = @hash($hashAlgorithm, $str4Fingerprint);
            $calcXHTMLFingerprint = @hash($hashAlgorithm, $str4XHMTLFingerprint);

            if (strlen($qpay['requestFingerprint']) === 128 && strcasecmp($calcFingerprint, $qpay['requestFingerprint']) !== 0 && strcasecmp($calcXHTMLFingerprint, $qpay['requestFingerprint']) !== 0) {
                $hmac_upscaling = true;
                $hashAlgorithm = 'hmac_sha512';
                $calcFingerprint = @hash_hmac('sha512', $str4Fingerprint, $qpay['merchantInformation']['merchantSecret']);
                $calcXHTMLFingerprint = @hash_hmac('sha512', $str4XHMTLFingerprint, $qpay['merchantInformation']['merchantSecret']);
            }
        }

        if (strcasecmp($calcFingerprint, $qpay["requestFingerprint"]) != 0 &&
            strcasecmp($calcXHTMLFingerprint, $qpay["requestFingerprint"]) != 0
        ) {
            doLog($page, 'NONE', 'Hash-Algorithm: ' . $hashAlgorithm);
            doLog($page, "NONE", "FP-Source: " . $str4Fingerprint);
            doLog($page, "NONE", "FP-Calc: " . $calcFingerprint);
            doLog($page, "NONE", "FP-Source-XHTML: " . $str4XHMTLFingerprint);
            doLog($page, "NONE", "FP-Calc-XHTML: " . $calcXHTMLFingerprint);
            doLog($page, "NONE", "FP-Req : " . $qpay["requestFingerprint"]);

            $errorCodes[] = PARAMETER_INVALID + FINGERPRINT;
        } else {
            // store algorithm for response fingerprint generation
            $qpay['hashAlgorithm'] = $hashAlgorithm;

            if (strcasecmp($calcFingerprint, $qpay["requestFingerprint"]) == 0) {
                // Validated fingerprint as is. Normal case, no logging
            } elseif (strcasecmp($calcXHTMLFingerprint, $qpay["requestFingerprint"]) == 0) {
                doLog($page, "NONE", "Validated fingerprint with XHTML-transformation on urls.");
            }

            if ($hmac_upscaling === true && $qpay['merchantInformation']['hashAlgorithm'] !== 'MERCHANT') {
                $message = 'HMAC_SHA512 upscaling in use, please update merchant configuration for ' . $qpay['merchantInformation']['customerNumber'] . ':' . $qpay['merchantInformation']['shopId'];
                doLog($page, 'NONE', $message);
            }

            if (isset($qpay["duplicateRequestCheck"]) && $qpay["duplicateRequestCheck"] != "") {
                if (strcasecmp(trim($qpay["duplicateRequestCheck"]), "YES") == 0 ||
                    strcasecmp(trim($qpay["duplicateRequestCheck"]), "ON") == 0 ||
                    strcasecmp(trim($qpay["duplicateRequestCheck"]), "TRUE") == 0
                ) {

                    /**
                     * @var $duplicateRequestCheckService DuplicateRequestCheck\Service\DuplicateRequestCheckService
                     */
                    $duplicateRequestCheckService = Zf2Singleton::getService('DuplicateRequestCheck\Service\DuplicateRequestCheckService');
                    $sessionID = $duplicateRequestCheckService->generateUniqueSessionId($qpay["customerId"], $qpay["requestFingerprint"]);

                    $baseSessionID = $qpay["customerId"] . md5($qpay["requestFingerprint"]);
                    if ($baseSessionID !== $sessionID) {
                        doLog($page, "NONE", "Session already exists in mongoDB: " . $baseSessionID);
                        $errorCodes[] = PARAMETER_DUPLICATED + FINGERPRINT;
                    }
                } elseif (strcasecmp(trim($qpay["duplicateRequestCheck"]), "NO") == 0 ||
                    strcasecmp(trim($qpay["duplicateRequestCheck"]), "OFF") == 0 ||
                    strcasecmp(trim($qpay["duplicateRequestCheck"]), "FALSE") == 0
                ) {
                    // not wanted, but ok from content meaning
                } else {
                    doLog(
                        $page,
                        "WARN",
                        "Got invalid value for duplicateRequestCheck: " . $qpay["duplicateRequestCheck"]
                    );
                    $errorCodes[] = PARAMETER_INVALID + DUPLICATEREQUESTCHECK;
                    unset($qpay["duplicateRequestCheck"]);
                }
            }
        }
    }
}

if (!isset($qpay["backgroundColor"])) {
    // default value in case of an error
    $qpay["backgroundColor"] = "F5F5F5";
}

// do some merchant/shop dependent settings
if (isset($qpay["merchantInformation"])) {

    $qpay["settings"]["QPAY_HTPATH"] = $CHECKOUT_HTPATH . '/page';
    $qpay["settings"]["MERCHANT_HTPATH"] = $qpay["settings"]["QPAY_HTPATH"] . "/" . $qpay["merchantInformation"]["identifier"] . '_' . $qpay['layout'];
    $qpay['settings']['MIN_PAN_EXPIRATION'] = $MIN_PAN_EXPIRATION;
    $qpay["settings"]["MAX_PAN_EXPIRATION"] = $MAX_PAN_EXPIRATION;
    $qpay["settings"]["MIN_PAN_ISSUE_DATE"] = $MIN_PAN_ISSUE_DATE;
    $qpay["settings"]["CERTIFICATE"] = "<!-- Certificate place --><!-- /Certificate place -->";
    if ($qpay["merchantInformation"]["siteseal"] == "Y") {
        // we have to use the consumer visible domain name for site seals (MID=7678)
        $urlParts = parse_url($qpay["settings"]["MERCHANT_HTPATH"]);
        $serverName = 'invalid';
        if ($urlParts !== false && isset($urlParts['host'])) {
            $serverName = $urlParts['host'];
        }
        $qpay["settings"]["CERTIFICATE"] = getCertificateSiteSeal(
            $qpay["language"],
            $serverName,
            $qpay["merchantInformation"]["layoutProfile"]
        );
    }
}


// valid merchant, no errors or recovarable error and merchant-dir exists
if (isset($qpay["merchantInformation"]) && (count($errorCodes) == 0 || ($errorIsRecoverable && file_exists($CHECKOUT_PAGE_PATH . '/' . $qpay["merchantInformation"]["identifier"] . '_' . $qpay['layout'])))) {
    //only go through PTs if currency and amount are valid MID: 8236
    if (array_key_exists('currency', $qpay['order']) && array_key_exists('amount', $qpay['order'])) {
        //overwrite some amount depentend configurations e.G. SSL Limit for CCARD brands
        $paysysConfigs = $qpay['merchantInformation']['paysysConfig'];
        foreach ($paysysConfigs as $paysysIndex => $paysysConfig) {
            foreach ($paysysConfig as $gatewayIndex => $gatewayConfig) {
                if (!empty($gatewayConfig['PAYMENT_OPTIONS'])) {
                    $sslLimits = (isset($gatewayConfig['PAYMENT_OPTIONS']['sslLimit'])) ? $gatewayConfig['PAYMENT_OPTIONS']['sslLimit'] : array();
                    if (array_key_exists($qpay['order']['currency'], $sslLimits)) {
                        $amountLimit = $sslLimits[$qpay['order']['currency']];
                        if ($amountLimit > stringToFloat($qpay['order']['amount'])) {
                            //Check if 3DS only (Y,Y,*,*)
                            if ($gatewayConfig['OPTIONS'][0] == 'Y' && $gatewayConfig['OPTIONS'][1] == 'Y') {
                                switch (strtolower($gatewayConfig['BRAND'])) {
                                    case 'visa':
                                        if (isset($useVbv) && $useVbv == true) {
                                            logBrandSetTo3DSButAmountBelowLimitUsing3DSOnly($page, $gatewayConfig, $amountLimit);
                                        } else {
                                            logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                            $gatewayConfig['OPTIONS'][0] = 'N';
                                            $gatewayConfig['OPTIONS'][1] = 'N';
                                        }
                                        break;
                                    case 'mc':
                                        if (isset($useMcsc) && $useMcsc == true) {
                                            logBrandSetTo3DSButAmountBelowLimitUsing3DSOnly($page, $gatewayConfig, $amountLimit);
                                        } else {
                                            logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                            $gatewayConfig['OPTIONS'][0] = 'N';
                                            $gatewayConfig['OPTIONS'][1] = 'N';
                                        }
                                        break;
                                    case 'amex':
                                        if (isset($useAesk) && $useAesk == true) {
                                            logBrandSetTo3DSButAmountBelowLimitUsing3DSOnly($page, $gatewayConfig, $amountLimit);
                                        } else {
                                            logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                            $gatewayConfig['OPTIONS'][0] = 'N';
                                            $gatewayConfig['OPTIONS'][1] = 'N';
                                        }
                                        break;
                                    case 'diners':
                                        if (isset($useDinersClubProtectBuy) && $useDinersClubProtectBuy == true) {
                                            logBrandSetTo3DSButAmountBelowLimitUsing3DSOnly($page, $gatewayConfig, $amountLimit);
                                        } else {
                                            logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                            $gatewayConfig['OPTIONS'][0] = 'N';
                                            $gatewayConfig['OPTIONS'][1] = 'N';
                                        }
                                        break;
                                    case 'discover':
                                        if (isset($useDiscoverProtectBuy) && $useDiscoverProtectBuy == true) {
                                            logBrandSetTo3DSButAmountBelowLimitUsing3DSOnly($page, $gatewayConfig, $amountLimit);
                                        } else {
                                            logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                            $gatewayConfig['OPTIONS'][0] = 'N';
                                            $gatewayConfig['OPTIONS'][1] = 'N';
                                        }
                                        break;
                                    default:
                                        logBrandSetTo3DSAmountBelowSSLUsingSSL($page, $gatewayConfig, $amountLimit);
                                        $gatewayConfig['OPTIONS'][0] = 'N';
                                        $gatewayConfig['OPTIONS'][1] = 'N';
                                        break;
                                }
                            } elseif ($gatewayConfig['OPTIONS'][0] == 'N') {
                                doLog(
                                    $page,
                                    'NONE',
                                    sprintf(
                                        'Brand %s is set to SSL only and amount is below SSL Limit %s. Using SSL only.',
                                        $gatewayConfig['BRAND'],
                                        $amountLimit
                                    )
                                );
                            } else {
                                //SSL
                                doLog(
                                    $page,
                                    'NONE',
                                    sprintf(
                                        'Brand %s allows SSL fallback and amount is below SSL Limit %s. Using SSL only.',
                                        $gatewayConfig['BRAND'],
                                        $amountLimit
                                    )
                                );
                                $gatewayConfig['OPTIONS'][0] = 'N';
                            }
                        } else {
                            if ($gatewayConfig['OPTIONS'][0] == 'Y' && $gatewayConfig['OPTIONS'][1] == 'N') {
                                //3DS
                                doLog(
                                    $page,
                                    'NONE',
                                    sprintf(
                                        'Brand %s allows SSL fallback and amount is higher than SSL Limit %s. Using 3DS+FB.',
                                        $gatewayConfig['BRAND'],
                                        $amountLimit
                                    )
                                );
                            } elseif ($gatewayConfig['OPTIONS'][0] == 'N') {
                                doLog(
                                    $page,
                                    'NONE',
                                    sprintf(
                                        'Brand %s is set to SSL only and amount is higher than SSL Limit %s. Using SSL only.',
                                        $gatewayConfig['BRAND'],
                                        $amountLimit
                                    )
                                );
                            } else {
                                doLog(
                                    $page,
                                    'NONE',
                                    sprintf(
                                        'Brand %s is set to 3DS only and amount is higher than SSL Limit %s. Using 3DS only.',
                                        $gatewayConfig['BRAND'],
                                        $amountLimit
                                    )
                                );
                            }
                        }
                    }
                }
                $paysysConfig[$gatewayIndex] = $gatewayConfig;
            }

            $paysysConfigs[$paysysIndex] = $paysysConfig;
        }
    }

    $qpay['merchantInformation']['paysysConfig'] = (!empty($paysysConfigs) ? $paysysConfigs : array());

    // now we can start the session

    // make a copy of the qpay-array
    $qpayCopy = $qpay;
    if (isset($sessionID) && $sessionID != '') {
        doLog('DEBUG', 'DEBUG', 'Using SID [' . $sessionID . ']');

        session_id($sessionID);
    } elseif (array_key_exists('SID', array_change_key_case($qpay['additionalParameters'], CASE_UPPER))) {
        //we have to generate a SID, because SID is given as additioal POST-Parameter - MID: 8527
        $sessionID = generateSessionId();

        doLog('DEBUG', 'DEBUG', 'Using generated SID [' . $sessionID . ']');
        session_id($sessionID);
    }

    session_save_path($SESSION_SAVE_PATH); // can be removed after migration to database sessions
    include_once 'inc/sessionHandler.include.php';
    session_start();
    $_SESSION["SESSION_VALID"] = true;
    $_SESSION['LAST_ALLOWED_PAGE'] = basename($_SERVER['SCRIPT_FILENAME']);
    register_shutdown_function('logPageDuration', $fStartTime, SID);

    Zf2Singleton::setLegacyConfig(array(
        Zf2Singleton::LEGACY_CONFIG_PAGE => $page,
        Zf2Singleton::LEGACY_CONFIG_OPTION_SESSION => SID,
        Zf2Singleton::LEGACY_CONFIG_MERCHANT_INFORMATION => $qpay['merchantInformation']
    ));

    // use the copied version of the qpay-array
    $qpay = $qpayCopy;

    // extend the session info to ht path (MID=6676)
    //$qpay["settings"]["MERCHANT_HTPATH"] .= '/' . session_id();

    // everything was checked and ok, now we can prepare for the real payment
    // the nextpage selection is always done inside the select-script

    // after started session, we can give a window name, if not given from merchant
    if (!isset($qpay["windowName"]) || trim($qpay["windowName"]) == "") {
        $qpay["windowName"] = "WCP" . md5(session_id());
    }


    // make a journal entry
    $qpay["initParameters"] = serialize($_POST);
    $qpay["initTimestamp"] = date("Y-m-d H:i:s");
    $qpay['consumerInformation']['ipAddress'] = $_SERVER['REMOTE_ADDR'];
    $qpay['consumerInformation']['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
    $qpay["journal"]["entry"] = doTransactionJournal($qpay, SID, serialize(sanitizeArray($initParamsForJournal)));

    // it is possible that we have to update the journal already with a warning-message
    if (strlen($warningMessageForJournal)) {
        updateTransactionJournal($qpay["journal"]["entry"], "PROGRESS", 0, $warningMessageForJournal);
    }

    if (count($errorCodes)) {
        //we have to invalidate the amount because no transaction should take place at all MID: 10633
        $qpay['order']['amount'] = -1;

        //MID: 10640
        //log which server already has the correct template version. array key for uniqueness
        $qpay['templateInformation']['servers'] = array(gethostname() => true);
        $qpay['templateInformation']['productVersion'] = $PRODUCT_VERSION;

        // we have to construct the right message
        $failureMessage = "";
        for ($i = 0; $i < count($errorCodes); $i++) {
            if ($errorCodes[$i] != PARAMETER_MISSING_USERDEFINED) {
                $failureMessage .= getMessage(ERRORCODE, $errorCodes[$i], "en") . " ";
            }
        }
        for ($i = 0; $i < count($optionalFields); $i++) {
            // we have to encode user definined parameters for later displaying (MID=5363).
            $failureMessage .= getMessage(ERRORCODE, PARAMETER_MISSING, "en", htmlentities($optionalFields[$i])) . " ";
        }

        $qpay["failureMessage"] = $failureMessage;


        $nextpage = $qpay["settings"]["MERCHANT_HTPATH"] . "/failureintermediate.php?" . SID;

        doLog($page, SID, "Failure during initiation: " . $qpay["failureMessage"]);
        // set empty values for error-redirect
        $qpay["merchantImage95x65"] = "";
        $qpay["merchantImage200x100"] = "";
        $qpay["merchantImageAsIs"] = "";

        // remove the merchant image in the tmp directory in every case
        if (isset($qpay["merchantImage-Temp"])) {
            @unlink($qpay["merchantImage-Temp"]);
        }

        // save page url of $nextpage to session so we can read it in basket.php
        $qpay['initialPaymentUrl'] = $nextpage;

        $_SESSION["qpay"] = $qpay;
        doLog("DEBUG", "DEBUG", serialize($_SESSION));
        session_write_close();

        // if we need to display a basket, etc. we override $nextpage
        // the $nextpay URL that basket.php needs is stored in $qpay['initialPaymentUrl']
        // that way we avoid having to construct $nextpage again in basket.php
        if ($qpay['displayBasketData'] || $qpay['displayShippingData'] || $qpay['displayBillingData']) {
            doLog($page, SID, 'displayBasketData: '.var_export($qpay['displayBasketData'], true).' displayShippingData: '.var_export($qpay['displayShippingData'], true).' displayShippingData: '.var_export($qpay['displayBillingData'], true));
            $nextpage = $qpay['settings']['MERCHANT_HTPATH'].'/basket.php?'.SID;
        }

        header("Location: " . $nextpage);
        exit;
    } else {
        $sourceDir = "./inc/merchantTemplate"; // var, $DEFAULT_TEMPLATE_DIR;
        $destinationDir = $CHECKOUT_PAGE_PATH . '/' . $qpay["merchantInformation"]["identifier"] . '_' . $qpay['layout'];

        include_once('./inc/TemplateStatus.class.php');
        include_once('./inc/QpayTemplateEntity.php');
        $oTemplateStatus = new TemplateStatus(array(TemplateStatus::CONFIG_TEMPLATE_FOLDER => $destinationDir));

        $entity = new QpayTemplateEntity();
        $entity->setCustomerId($qpay["customerId"])->setShopId($qpay["shopId"])->setLayout($qpay['layout']);
        $aVersions = $entity->getEnabledTemplateVersion($qpay['layout']);

        $bCopyApp = false;
        $bCopyLayout = false;
        $bCopyTemplate = false;

        //log which server already has the correct template version. Array key for uniqueness
        $qpay['templateInformation']['servers'] = array(gethostname() => true);
        $qpay['templateInformation']['productVersion'] = $PRODUCT_VERSION;

        if (count($aVersions)) {
            $qpay['templateInformation']['templateVersion'] = $aVersions[0];
        }

        // if ($QPAY_TESTSYSTEM_MODE) {
        //     $bCopyApp = true;
        //     $bCopyLayout = true;
        //     $bCopyTemplate = true;
        //     doLog($page, SID, sprintf('Force 1st call on test system.'));
        // } else {


          /**
           * To force copying of folders, remove them on dev server: e.g. rm -fr /var/www/products/wd-httpd-php-wcp-frontend/D200901*
           */
          
            try {
                if (!$oTemplateStatus->isCurrentApplicationVersion($PRODUCT_VERSION)) {

                    $bCopyApp = true;
                    $bCopyLayout = true;
                    $bCopyTemplate = true;
                    doLog(
                        $page,
                        SID,
                        sprintf(
                            'Application update from %s to %s. Force 1st call.',
                            $oTemplateStatus->getCurrentApplicationVersion(),
                            $PRODUCT_VERSION
                        )
                    );
                } elseif (!$oTemplateStatus->isCurrentLayoutName($qpay['merchantInformation']['layoutProfile'])) {
                    $bCopyApp = true;
                    $bCopyLayout = true;
                    $bCopyTemplate = true;
                    doLog(
                        $page,
                        SID,
                        sprintf(
                            'Layout changed from %s to %s. Copying layout.',
                            $oTemplateStatus->getCurrentLayoutName(),
                            $qpay['merchantInformation']['layoutProfile']
                        )
                    );
                    if ($qpay["merchantInformation"]["layoutProfile"] == "QPAY30") {
                        doLog($page, SID, 'Layout QPAY30 selected. Using Application files.');
                    }
                } elseif ($qpay["merchantInformation"]["templates"] == 'N' && $oTemplateStatus->getCurrentTemplateVersion()) {
                    $bCopyApp = true;
                    $bCopyLayout = true;
                    doLog($page, SID, sprintf('Template copyed but no template enabled. Overwriting with Layout files.'));
                    $oTemplateStatus->unsetTemplate();
                    if ($qpay["merchantInformation"]["layoutProfile"] == "QPAY30") {
                        doLog($page, SID, 'Layout QPAY30 selected. Using Application files.');
                    }
                } elseif (count($aVersions) > 0 && !$oTemplateStatus->isCurrentTemplateVersion($aVersions[0])) {
                    $bCopyTemplate = true;
                    doLog($page, SID, sprintf('Using new Template Version %s', $aVersions[0]));
                }
            } catch (Exception $e) {
                $bCopyApp = true;
                $bCopyLayout = true;
                $bCopyTemplate = true;
                doLog($page, SID, $e->getMessage());
            }
 //       }

        if ($bCopyApp) {
            try{
                copyDirectory($sourceDir, $destinationDir);
                $oTemplateStatus->setApplicationVersion($PRODUCT_VERSION);
            }catch (Exception $e){
                doLog($page, SID, $e->getMessage() . ": " . $e->getTraceAsString() . ": CopyApp-segment");
                throw($e->getMessage() . ": " . $e->getTraceAsString() . ": CopyApp-segment");
            }
        }

        if ($bCopyLayout) {
            try{
                $sLayoutProfile = $qpay["merchantInformation"]["layoutProfile"];
                if ($qpay["merchantInformation"]["layoutProfile"] != "QPAY30") {
                    $sourceDir = $CHECKOUT_PAGE_PATH . "/templates/profile_" . $qpay["merchantInformation"]["layoutProfile"];
                    if (copyDirectory($sourceDir, $destinationDir)) {
                        doLog($page, SID, "Copying layout profile templates from: " . $sourceDir);
                    }
                }
                $oTemplateStatus->setLayoutName($sLayoutProfile);
            }catch (Exception $e){
                doLog($page, SID, $e->getMessage() . ": " . $e->getTraceAsString() . ": bCopyLayout-segment");
                throw($e->getMessage() . ": " . $e->getTraceAsString() . ": bCopyLayout-segment");
            }
        }

        if ($bCopyTemplate && $qpay["merchantInformation"]["templates"] == "Y") {
            try {
                $templates = $entity->getTemplates($qpay['layout'], true);
                if (count($templates) > 0 && !is_null($templates[0])) {
                    $tempFile = tempnam(sys_get_temp_dir(), $qpay["customerId"]);
                    if (file_put_contents($tempFile, $templates[0]->CONTENT)) {
                        $zip = new ZipArchive();
                        if ($zip->open($tempFile) === true) {
                            $zip->extractTo($destinationDir);
                            $zip->close();
                            $oTemplateStatus->setTemplateVersion($templates[0]->VERSION);
                            unlink($tempFile);
                        }
                    }
                }
            } catch (Exception $e) {
                doLog($page, SID, $e->getMessage() . ": " . $e->getTraceAsString() . ": bCopyTemplate-segment");
                throw($e->getMessage() . ": " . $e->getTraceAsString() . ": bCopyTemplate-segment");
            }
        }

        if ($bCopyApp || $bCopyLayout || $bCopyTemplate) {
            $oTemplateStatus->writeStatusFile();
        }


        $sMerchantPath = $CHECKOUT_PAGE_PATH . '/' . $qpay["merchantInformation"]["identifier"] . '_' . $qpay['layout'];

        if (array_key_exists('cssUrl', $qpay) && $qpay['cssUrl'] !== '') {
            $qpay['merchantCssName'] = 'merchantCss_' . substr($qpay['cssUrl'], strrpos($qpay['cssUrl'], '/') + 1);
            $qpay['merchantCssFullPath'] = $CHECKOUT_PAGE_PATH . '/' . $qpay['merchantInformation']['identifier'] . '_' . $qpay['layout'] . '/styles/' . $qpay['merchantCssName'];

            if (file_exists($qpay['merchantCssFullPath']) && $qpay['merchantInformation']['useCachedMerchantImage'] == 'Y') {
                doLog($page, 'NONE', 'Using already existing merchantCss on filesystem.');
            } else {

                $aClientConfiguration = array(
                    'confirmationTimeout' => $qpay['merchantInformation']['notificationTimeout'],
                    'confirmationSslVersion' => (isset($qpay['merchantInformation']['notificationSslVersion']) ?
                        $qpay['merchantInformation']['notificationSslVersion'] : 'AUTO'
                    ),
                );

                $sCopyMerchantCssMessage = '';
                if (!copyRemoteMerchantImage($qpay['cssUrl'], $qpay['merchantCssFullPath'], $sCopyMerchantCssMessage, $aClientConfiguration)) {
                    doLog($page, 'WARN', 'Css-Url invalid: ' . $qpay['cssUrl'] . ', Reason: Copy error: ' . $sCopyMerchantCssMessage);

                    // reset the parameters
                    $qpay['merchantCssName'] = '';
                }
            }
        }

        if (isset($qpay["merchantImage-Temp"]) && $qpay["merchantImage-Temp"] != "") {
            // we have to move the image from temp to merchant
            $qpay["merchantImageFile95x65"] = $sMerchantPath . "/img/merchantImage_95x65_" . $qpay["merchantImage-Name"];
            $qpay["merchantImageFile200x100"] = $sMerchantPath . "/img/merchantImage_200x100_" . $qpay["merchantImage-Name"];
            $qpay["merchantImageFileAsIs"] = $sMerchantPath . "/img/merchantImage_AsIs_" . $qpay["merchantImage-Name"];

            $qpay["merchantImage95x65"] = "./sessionimage.php?imageType=merchantImage95x65&" . SID;
            $qpay["merchantImage200x100"] = "./sessionimage.php?imageType=merchantImage200x100&" . SID;
            $qpay["merchantImageAsIs"] = "./sessionimage.php?imageType=merchantImageAsIs&" . SID;

            //creating temporary files to preserve old implementation and make CachedMerchantImage possible
            copyAndResizeMerchantImage($qpay["merchantImage-Temp"], $qpay["merchantImageFile95x65"], 95, 65);
            copyAndResizeMerchantImage($qpay["merchantImage-Temp"], $qpay["merchantImageFile200x100"], 200, 100, true);
            copyAndResizeMerchantImage(
                $qpay["merchantImage-Temp"],
                $qpay["merchantImageFileAsIs"]
            ); // means leave sizes untouched

        } elseif (isset($qpay['merchantImageAlreadyExists']) && $qpay['merchantImageAlreadyExists']) {
            // we can use the existing files
            $qpay["merchantImageFile95x65"] = $sMerchantPath . "/img/merchantImage_95x65_" . $qpay["merchantImage-Name"];
            $qpay["merchantImageFile200x100"] = $sMerchantPath . "/img/merchantImage_200x100_" . $qpay["merchantImage-Name"];
            $qpay["merchantImageFileAsIs"] = $sMerchantPath . "/img/merchantImage_AsIs_" . $qpay["merchantImage-Name"];

            $qpay["merchantImage95x65"] = "./sessionimage.php?imageType=merchantImage95x65&" . SID;
            $qpay["merchantImage200x100"] = "./sessionimage.php?imageType=merchantImage200x100&" . SID;
            $qpay["merchantImageAsIs"] = "./sessionimage.php?imageType=merchantImageAsIs&" . SID;
        } else {
            $qpay["merchantImage95x65"] = "";
            $qpay["merchantImage200x100"] = "";
            $qpay["merchantImageAsIs"] = "";
        }

        if (!empty($qpay['merchantImageFile95x65']) && file_exists($qpay['merchantImageFile95x65'])) {
            $qpay['images']['merchantImage95x65'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($qpay['merchantImageFile95x65'])),
                'data' => base64_encode(file_get_contents($qpay['merchantImageFile95x65']))
            );
        }
        if (!empty($qpay['merchantImageFile200x100']) && file_exists($qpay['merchantImageFile200x100'])) {
            $qpay['images']['merchantImage200x100'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($qpay['merchantImageFile200x100'])),
                'data' => base64_encode(file_get_contents($qpay['merchantImageFile200x100']))
            );
        }
        if (!empty($qpay['merchantImageFileAsIs']) && file_exists($qpay['merchantImageFileAsIs'])) {
            $qpay['images']['merchantImageAsIs'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($qpay['merchantImageFileAsIs'])),
                'data' => base64_encode(file_get_contents($qpay['merchantImageFileAsIs']))
            );
        }

        if (!isset($qpay['displayOptions']['paymentTypes'])) {
            $qpay['displayOptions']['paymentTypes'] = array();
        }
        $compiledPaymentTypeSortOrder = compileSortOrder($qpay['displayOptions']['paymentTypes'], $qpay["merchantInformation"]["paysysConfig"]);

        // query payment types we can display for this merchant
        $qpay["paymentTypes"] = getAvailablePaymentTypes($qpay["merchantInformation"]["paysysConfig"], $qpay["language"], $compiledPaymentTypeSortOrder);

        prepareBrandImages(
            $qpay["merchantInformation"]["paysysConfig"],
            $qpay["backgroundColor"],
            $sMerchantPath,
            $qpay["merchantInformation"]["specialIssuer"]
        );
        if (file_exists($sMerchantPath . '/img/paysys_ccard_vertical.gif')) {
            $qpay['images']['paysys_ccard_vertical'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard_vertical.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard_vertical.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard_horizontal.gif')) {
            $qpay['images']['paysys_ccard_horizontal'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard_horizontal.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard_horizontal.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard_200x220.gif')) {
            $qpay['images']['paysys_ccard_200x220'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard_200x220.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard_200x220.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard_350x30.gif')) {
            $qpay['images']['paysys_ccard_350x30'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard_350x30.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard_350x30.gif'))
            );
        }

        if (file_exists($sMerchantPath . '/img/paysys_ccard-moto_vertical.gif')) {
            $qpay['images']['paysys_ccard-moto_vertical'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard-moto_vertical.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard-moto_vertical.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard-moto_horizontal.gif')) {
            $qpay['images']['paysys_ccard-moto_horizontal'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard-moto_horizontal.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard-moto_horizontal.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard-moto_200x220.gif')) {
            $qpay['images']['paysys_ccard-moto_200x220'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard-moto_200x220.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard-moto_200x220.gif'))
            );
        }
        if (file_exists($sMerchantPath . '/img/paysys_ccard-moto_350x30.gif')) {
            $qpay['images']['paysys_ccard-moto_350x30'] = array(
                'contentType' => image_type_to_mime_type(exif_imagetype($sMerchantPath . '/img/paysys_ccard-moto_350x30.gif')),
                'data' => base64_encode(file_get_contents($sMerchantPath . '/img/paysys_ccard-moto_350x30.gif'))
            );
        }

        if ($qpay["maxRetries"] >= 0) {
            // log only, if max retries limited
            doLog($page, SID, "Using max " . $qpay["maxRetries"] . " retries.");
        }
        if($qpay['additionalParameters']['type'] == "qmore"){
            ob_start();
            include './inc/merchantTemplate/googlepayqmore.php';
            $response = ob_get_contents();
            ob_end_clean();
            echo $response;
        }
        $nextpage = $qpay["settings"]["MERCHANT_HTPATH"] . "/select.php?" . SID;

        // remove the merchant image in the tmp directory in every case
        if (isset($qpay["merchantImage-Temp"])) {
            @unlink($qpay["merchantImage-Temp"]);
        }

        // save page url of $nextpage to session so we can read it in basket.php
        $qpay['initialPaymentUrl'] = $nextpage;

        $_SESSION["qpay"] = $qpay;
        session_write_close();

        // if we need to display a basket, etc. we override $nextpage
        // the $nextpay URL that basket.php needs is stored in $qpay['initialPaymentUrl']
        // that way we avoid having to construct $nextpage again in basket.php
        if ($qpay['displayBasketData'] || $qpay['displayShippingData'] || $qpay['displayBillingData']) {
          doLog($page, SID, 'displayBasketData: '.var_export($qpay['displayBasketData'], true).' displayShippingData: '.var_export($qpay['displayShippingData'], true).' displayShippingData: '.var_export($qpay['displayBillingData'], true));
          $nextpage = $qpay['settings']['MERCHANT_HTPATH'].'/basket.php?'.SID;
        }
        doLog($page, SID, "Successfull initiation, redirect customer to: " . $nextpage);
        Header("Location: " . $nextpage);
        exit;
    }
} else {
    // if the script reaches this point, we can print all errors we have

    if ($errorIsRecoverable &&
        isset($qpay["settings"]) &&
        !file_exists($CHECKOUT_PAGE_PATH . '/' . $qpay["merchantInformation"]["identifier"] . '_' . $qpay['layout'])
    ) {
        doLog($page, 'NONE', 'Unable to redirect to failureUrl. Merchant directory not yet created.');
    }

    register_shutdown_function('logPageDuration', $fStartTime, 'NONE');

    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <!-- (c) Copyright <?= date("Y") ?> by QENTA CEE, All rights reserved -->
        <title>QPAY Checkout Page - Initiation</title>
        <meta http-equiv="Content-type" content="text/html;charset=iso-8859-1"/>
    </head>
    <body>
    <table>
        <?
        // make a journal entry
        $qpay["initTimestamp"] = date("Y-m-d H:i:s");
        $qpay['consumerInformation']['ipAddress'] = $_SERVER['REMOTE_ADDR'];
        $qpay['consumerInformation']['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        $qpay["journal"]["entry"] = doTransactionJournal(
            $qpay,
            "NONE",
            serialize(sanitizeArray($initParamsForJournal))
        );

        // if FATAL_CANNOT_CONTINUE is included in the errorcodes, this message is the only we can use
        if (in_array(FATAL_CANNOT_CONTINUE, $errorCodes)) {
            $errorCodes = array(FATAL_CANNOT_CONTINUE);
            $optionalFields = array();
            $reservedWordsUsed = array();
        }

        $errorMessageForJournal = "";

        for ($i = 0; $i < count($errorCodes); $i++) {
            $errorCode = $errorCodes[$i];
            if ($errorCode != PARAMETER_MISSING_USERDEFINED) {
                if ($errorCode == PARAMETER_NOT_ALLOWED) {
                    // skip, the failure output is done later
                    continue;
                }
                ?>
                <tr>
                    <td style="font-family: Arial, Helvetica, Verdana, Geneva, sans-serif; color: #ff0000; font-size: 12px;">
                        <?
                        echo getMessage(ERRORCODE, $errorCode, $qpay["language"]) . "\n";
                        doLog($page, "NONE", getMessage(ERRORCODE, $errorCode, "en"));
                        $errorMessageForJournal .= getMessage(ERRORCODE, $errorCode, "en") . " ";
                        ?>
                    </td>
                </tr>
                <?
            }
        }

        for ($i = 0; $i < count($optionalFields); $i++) {
            ?>
            <tr>
                <td style="font-family: Arial, Helvetica, Verdana, Geneva, sans-serif; color: #ff0000; font-size: 12px;">
                    <?
                    echo getMessage(ERRORCODE, PARAMETER_MISSING, $qpay["language"], $optionalFields[$i]) . "\n";
                    doLog($page, "NONE", getMessage(ERRORCODE, PARAMETER_MISSING, "en", $optionalFields[$i]));
                    $errorMessageForJournal .= getMessage(ERRORCODE, PARAMETER_MISSING, "en", $optionalFields[$i]) . " ";
                    ?>
                </td>
            </tr>
            <?
        }

        for ($i = 0; $i < count($reservedWordsUsed); $i++) {
            ?>
            <tr>
                <td style="font-family: Arial, Helvetica, Verdana, Geneva, sans-serif; color: #ff0000; font-size: 12px;">
                    <?
                    echo getMessage(ERRORCODE, PARAMETER_NOT_ALLOWED, $qpay["language"], $reservedWordsUsed[$i]) . "\n";
                    doLog($page, "NONE", getMessage(ERRORCODE, PARAMETER_NOT_ALLOWED, "en", $reservedWordsUsed[$i]));
                    $errorMessageForJournal .= getMessage(ERRORCODE, PARAMETER_NOT_ALLOWED, "en", $reservedWordsUsed[$i]) . " ";
                    ?>
                </td>
            </tr>
            <?
        }
        ?>

    </table>
    </body>
    </html>
    <?
    // update the errornous init
    updateTransactionJournal($qpay["journal"]["entry"], "FAILURE", 0, $errorMessageForJournal);
}

// remove the merchant image in the tmp directory in every case
if (isset($qpay["merchantImage-Temp"])) {
    @unlink($qpay["merchantImage-Temp"]);
}
