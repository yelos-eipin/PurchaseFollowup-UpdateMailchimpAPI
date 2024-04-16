<?php
require_once('/home/rmata/scripts/cron/mailchimp/mailchimp-marketing-php-master/vendor/autoload.php');
require_once('/home/rmata/scripts/cron/mailchimp/parseCSV/vendor/autoload.php');

$mailchimp = new \MailchimpMarketing\ApiClient();
//$audienceList = "7fab3eb98c"; //DEV
$audienceList = "5aa7091abc"; //PCE PROD
$reportFiles = array("/home/rmata/scripts/cron/mailchimp/purchasefollowup/Reports/EquipmentPurchasesLast24Hours_FileOutput.csv",
                     "/home/rmata/scripts/cron/mailchimp/purchasefollowup/Reports/PartsPurchasesLast24Hours_FileOutput.csv",
                     "/home/rmata/scripts/cron/mailchimp/purchasefollowup/Reports/ServicePerformedLast24Hours_FileOutput.csv"
);

$mailchimp->setConfig([
	//'apiKey' => 'EnterAPIKeyHere-us1', //DEV
	//'server' => 'us1' //DEV
    'apiKey' => 'EnterAPIKeyHere-us14', //PCE PROD
    'server' => 'us14' //PCE PROD
]);

foreach ($reportFiles as $reportFile)    
{
    echo "Current Report file: $reportFile\n";
    //Read CSV report produced by JasperReports
    //and add each new entry into MailChimp list
    $csv = new \ParseCsv\Csv($reportFile);
    $csv->delimiter = ",";
    $purchaseType = "";
    
    if( str_contains($reportFile, 'Equipment') )
    {
        $purchaseType = 'Equipment';
    }elseif ( str_contains($reportFile, 'Service') ){
        $purchaseType = 'Service';
    }elseif (str_contains($reportFile, 'Parts')){
        $purchaseType = 'Parts';
    }
    
    foreach($csv->data as $line)
    {
        try{
        $email = strtolower(trim($line['Email']));
        $custname = $line['Customer Name'];
        $branch = $line['Branch'];
        $purchaseDate = new DateTime($line['Purchase Date']);
        $purchaseDateStr = $purchaseDate->format('d/m/Y');
        
        /*
        echo "AudienceList: " . $audienceList . "\n";
        echo "Customer Name: " . $custname . "\n";
        echo "Branch: " . $branch . "\n";
        echo "Email: ->" . $email . "<-\n";
        echo "Purchase Date: " . $purchaseDateStr . "\n";
        echo "Purchase Type: $purchaseType \n";
        */
        
            try{
                
                //$response = $mailchimp->lists->updateListMember($audienceList,hash('md5', $email) , [
                
                $response = $mailchimp->lists->addListMember($audienceList, [
                    "email_address" => "$email",
                    "status" => "subscribed",
                    "merge_fields" => ["CUSTNAME" => $custname,
                                        "BRANCH" => $branch,
                                        "PURCHDATE" => $purchaseDate],
                    "tags" => array($purchaseType),
                ]);
            
            }catch(exception $e){
                $errMessage = $e->getResponse()->getBody()->getContents();
                //$errMessage = $e->getMessage(); //Don't use this because it truncates error message
                
                //if contact already exists, update Purchase Date  -> "Member Exists"
                if ( str_contains($errMessage, "Member Exists") )
                {
                    echo "Email ($email) already exists. Updating record...\n";
                    //echo $errMessage . "\n";
                    try{
                        echo "Removing any existing tags for ($email)\n";
                        $response = $mailchimp->lists->updateListMemberTags($audienceList,hash('md5', $email) , [
                            //$response = $mailchimp->lists->addListMember($audienceList, [
                            "tags" => [ ["name" => "Parts", "status" => "inactive"],
                                        ["name" => "Service", "status" => "inactive"], 
                                        ["name" => "Equipment", "status" => "inactive"], ],
                        ]);
                    }catch(exception $e){
                        echo "Error while removing any existing tags\n";
                        echo $e->getMessage();
                    }
                    try{
                        echo "Updating record for ($email) with tag ($purchaseType)\n";
                        $response = $mailchimp->lists->updateListMember($audienceList,hash('md5', $email) , [
                            //$response = $mailchimp->lists->addListMember($audienceList, [
                            "email_address" => "$email",
                            "status" => "subscribed",
                            "merge_fields" => ["CUSTNAME" => $custname,
                                "BRANCH" => $branch,
                                "PURCHDATE" => $purchaseDate],
                            "tags" => [ ["name" => $purchaseType, "status" => "active"], ],
                        ]);
                        
                        $response = $mailchimp->lists->updateListMemberTags($audienceList,hash('md5', $email) , [
                            //$response = $mailchimp->lists->addListMember($audienceList, [
                            "tags" => [ ["name" => $purchaseType, "status" => "active"], ],
                        ]);
                    }catch(exception $e){
                        echo $e->getMessage();
                    }
                    
                    //if email is invalid title -> "Invalid Resource"
                }elseif ( str_contains($errMessage, "Invalid Resource") ) {
                    echo "Looks like there is an invalid email address ($email). Send email to fix it\n";
                    echo "$errMessage\n";
                }else{
                    echo "Error occurred. Should be put on a log\n";
                    echo $errMessage;
                }
            }
            
            echo "---------------------------------------------\n\n";
        
        }catch(exception $e){
           echo $e->getMessage() . "\n";
        }
        

    }
}


?>
