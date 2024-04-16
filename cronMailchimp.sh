#!/bin/bash

do_work() {
   #check if reports are up-to-date. Otherwise, it means JasperReports is probably not running.
   PARTSREPORT=/home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/Reports/PartsPurchasesLast24Hours_FileOutput.csv
   SERVICEREPORT=/home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/Reports/ServicePerformedLast24Hours_FileOutput.csv
   EQUIPMENTREPORT=/home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/Reports/EquipmentPurchasesLast24Hours_FileOutput.csv
   (
   if test `find $PARTSREPORT -mmin +120` || test `find $SERVICEREPORT -mmin +120` || test `find $EQUIPMENTREPORT -mmin +120`
   then
       #too old, throw error and exit script
      exit 1 
   else

      #push data to MailChimp's API
      /usr/bin/php /home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/updateMailChimpList.php > /home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/output.log

      #Check if there's an error 
      FILE=/home/rmata/scripts/cron/mailchimp/PurchaseFollowup-UpdateMailchimp/output.log
      if test -f "$FILE"; then
         line_count=$(grep Invalid $FILE | wc -l  )
         return $line_count
      else
         echo "inv_key"
         return 1
      fi
   fi
   ) || (
      echo "stale_report"
      return 1
   )

#   /usr/bin/php /home/rmata/scripts/cron/mailchimp/purchasefollowup/updateMailChimpList.php > /home/rmata/scripts/cron/mailchimp/purchasefollowup/output.log
#   return 0
}

report() {

  subject="Mailchimp list update"
  from="<no-reply@pcequip.ca>"
  recipients="rmata@pcequip.ca"

  [ "$1" = "ok" ] && echo "Success" | mail -s "$subject"  -r "$from" $recipients
  #[ "$1" != "ok" ] && echo "Error: $1" | mail -s "$subject"  -r "$from" $recipients
  [ "$1" = "inv_key" ] && echo "Error: Invalid key" | mail -s "$subject"  -r "$from" $recipients
  [ "$1" = "stale_rep" ] && echo "Error: Report not up to date (check JasperReports is running)" | mail -s "$subject"  -r "$from" $recipients

  return 0
}

do_work \
  && report ok || report stale_rep || report inv_key

