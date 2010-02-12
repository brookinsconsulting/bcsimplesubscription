<? /* #?ini charset="utf-8"?

# Simple Subscription Settings
#
# Most of these are ids based on your existing content
# and should be modified to match your own content's ids

[SimpleSubscriptionSettings]

# Subscription Group (Premium)
SubscriptionGroupNodeID=70

# Guest Group (Default)
SubscriptionGuestGroupNodeID=12

# Subscription Product
SubscriptionProductClassID=16

# Class Attribute Names
SubscriptionProductAttributeName=product_number
SubscriptionUserAttributeName=expire

# User ClassID (Cronjob Dependency)
UserClassID=4

# Admin UserID (Cronjob Dependency)
AdministratorUserID=14

# Send Emails to Users
SendSubscriptionExpirationNotificationEmails=enabled

# Enable Debug (Not recommended)
Debug=disabled

# SubscriptionProductClasses[]
# SubscriptionProductClasses[]=Product

# Expiration Cronjob Log
# LogDebug=disabled
# Log=var/log/bcsimplesubscription.log

*/ ?>