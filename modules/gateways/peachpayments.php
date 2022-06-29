<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function peachpayments_MetaData()
{
    return array(
        'DisplayName' => 'Peach Payments',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
        'failedEmail' => 'Credit Card Payment Failed',
        'successEmail' => 'Custom Credit Card Payment Template',
    );
}

function peachpayments_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Peach Payments',
        ),

        'entity_id' => array(
            'FriendlyName' => 'Entity Id',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter Entity Id here',
        ),
        // a password field type allows for masked text input
       'authorization_token' => array(
            'FriendlyName' => 'Authorization Token',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter Authorization Token here',
        ),
       'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
    );
}

function peachpayments_capture($params)
{
    // Gateway Configuration Parameters
    $entity_id = $params['entity_id'];
    $authorization_token = $params['authorization_token'];
    $testMode = $params['testMode'];

    // Credit card details
    $cardnum = $params['cardnum'];
    $cccvv = $params['cccvv'];
    $cardexp = $params['cardexp'];
    $cardtype = $params['cardtype'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    $systemUrl = $params['systemurl'];
    $moduleName = $params['paymentmethod'];

    $cardmonth=substr($cardexp,0,2);
    $cardyear='20'.substr($cardexp,2);

    if($cardtype=='MasterCard')
    {
        $cardtype='MASTER';
    }
    elseif($cardtype=='Visa')
    {
        $cardtype='VISA';
    }
    elseif($cardtype=='American Express')
    {
        $cardtype='AMEX';
    }
    
    $callback_url= $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';


    $data = array(
        "entityId" => $entity_id,
        "amount" => $amount,
        "currency" => $currencyCode,
        "paymentBrand" => $cardtype,
        "paymentType" => "DB",        
        "card.number" => $cardnum,
        "card.holder" => $params['payMethod']->description,
        "card.expiryMonth" => $cardmonth,
        "card.expiryYear" => $cardyear,
        "card.cvv" => $cccvv,        
        "shopperResultUrl" =>  $callback_url,
        "customParameters[invoiceId]" => $invoiceId,
        "customParameters[currency]" => $currencyCode,
        "customParameters[amount]" => $amount,
        "customParameters[systemUrl]" => $systemUrl);

    if($testMode=='on')
    {
        $url='https://test.oppwa.com/';
    }
    else
    {
        $url='https://oppwa.com/';
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url."v1/payments",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => http_build_query($data),
      CURLOPT_HTTPHEADER => array(
        "authorization: Bearer ".$authorization_token
      ),
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $err = curl_error($curl);

    curl_close($curl);

    $response=json_decode($response,true);
    logModuleCall('peachpayments', 'cpature', $data, $response);


    $transactionId=$response['id'];
    $paymentAmount=$response['amount'];
    //customParameters[SHOPPER_invoiceId];
    if(isset($response['redirect']['url']))
    {
        logModuleCall('peachpayments', 'cpature', $data, $response);
        $htmlOutput = "<div style='text-align: center; width:300px; position: fixed; top: 30%; left: 50%; margin-top: -50px; margin-left: -150px;'>";
        $htmlOutput .= '<h2>You will be redirected for 3ds verification shortly. </h2>';
        $htmlOutput .= '<form id="3dsecureform" method="post" action="'.$response['redirect']['url'].'">';
        foreach ($response['redirect']['parameters'] as $key => $value) {
            $htmlOutput.= ' <input type="hidden" name="'.$value['name'].'" value="'.$value['value'].'">';                
        }        
        $htmlOutput.= '</form>';
        $htmlOutput .= '<script type="text/javascript">setTimeout(function() { document.getElementById("3dsecureform").submit(); }, 2000);</script>';
        $htmlOutput .= '</div>';        
        echo $htmlOutput;
        exit;
    }

    if(in_array($response['result']['code'], array('000.000.000','000.000.100','000.100.105','000.100.106','000.100.110','000.100.111','000.100.112','000.300.000'))){
        
         $returnData = [
            'status' => 'success',
            'rawdata' => $response,
            'transid' => $transactionId,
            'fee' => $feeAmount,
        ];
    }
    else
    {
         $returnData = [
            'status' => 'declined',
            'declinereason' => 'Credit card declined. Please contact issuer.',
            'rawdata' => $response,
        ];
    }
    return $returnData;

}

?>