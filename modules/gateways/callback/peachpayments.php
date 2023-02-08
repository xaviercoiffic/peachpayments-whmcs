<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');

$gatewayParams = getGatewayVariables($gatewayModuleName);

if($gatewayParams['testMode']=='on')
{
    $url='https://eu-test.oppwa.com/';
}
else
{
    $url='https://eu-prod.oppwa.com/';
}

$curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url."v1/payments/".$_GET['id']."?entityId=".$gatewayParams['entity_id'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => http_build_query($data),
      CURLOPT_HTTPHEADER => array(
        "authorization: Bearer ".$gatewayParams['authorization_token']
      ),
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $err = curl_error($curl);

    curl_close($curl);

    $response=json_decode($response,true);

    	$success='Failure';
      $transactionStatus = 'Failure';
	if(in_array($response['result']['code'], array('000.000.000','000.000.100','000.100.105','000.100.106','000.100.110','000.100.111','000.100.112','000.300.000'))) {
		$success='Success';
    $transactionStatus = 'Success';
	}

    $systemUrl = $response['customParameters']['systemUrl'];
    $invoiceId = $response['customParameters']['invoiceId'];
    $transactionId = $response['id'];
    $paymentAmount = $response['customParameters']['amount'];
    $paymentFee = 0;
    $hash = "";

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($transactionId);


    logTransaction($gatewayParams['name'], $response, $transactionStatus);

    if($success=='Success')
    {
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $gatewayModuleName
        );
    }

header("location: ".$systemUrl."/viewinvoice.php?id=".$invoiceId);

?>
