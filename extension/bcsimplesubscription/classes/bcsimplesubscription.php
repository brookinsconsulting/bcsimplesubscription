<?php
//
// Definition of BCSimpleSubscription class
// Created on: <10-19-2007 23:42:02 gb>
//
// COPYRIGHT NOTICE: 2001-2007 Brookins Consulting. All rights reserved.
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0 (or later) of the GNU
//   General Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301,  USA.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html
//
// Contact licence@brookinsconsulting.com if any conditions
// of this licencing isn't clear to you.
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##

/*!
 \file bcsimplesubscription.php
*/

include_once( 'lib/ezutils/classes/ezhttptool.php' );
include_once( 'lib/ezutils/classes/ezexecution.php' );
include_once( 'kernel/classes/ezcache.php' );
include_once( 'kernel/classes/ezorder.php' );

include_once( 'kernel/classes/ezcontentclass.php' );
include_once( 'kernel/classes/ezcontentobject.php' );
include_once( 'kernel/classes/ezcontentobjecttreenode.php' );
include_once( 'kernel/classes/datatypes/ezuser/ezuser.php' );
include_once( 'kernel/classes/datatypes/ezdatetime/ezdatetimetype.php' );


/*!
 \class BCSimpleSubscription bcsimplesubscription.php
 \brief The class BCSimpleSubscription handles activating and deactivating user subscriptions
*/

class BCSimpleSubscription
{
    /*!
     Constructor
    */
    function BCSimpleSubscription()
    {
        // include_once( 'extension/ezdbug/autoloads/ezdbug.php' );
        // $d = new eZDBugOperators();
        // $d->ezdbugDump( false, 99, true );
    }

    /*!
     Check user expire attribute for expired membership
    */
    function isMembershipExpired( $user )
    {
        $ret = false;
        $dm = $user->dataMap();
        $userObject = $user->object();
        $userObjectName = $userObject->attribute( 'name' );
        $userObjectDataMap = $userObject->dataMap();
        $userObjectExpiry = $userObjectDataMap['expire'];
        $userObjectExpiryInteger = $userObjectDataMap['expire']->content();

        // fetch current datetime object
        $currentDateTime = new eZDateTime();
        $currentDateTimeString = $currentDateTime->toString();

        // fetch user expire datetime
        $userExpireObject = $userObjectExpiry;
        $userExpireDate = $userObjectExpiry->title();
        $userExpireObjectInt = $userExpireObject->DataInt;
        $userExpireDateTime = new eZDateTime();
        $userExpireDateTime->setTimeStamp( $userExpireObjectInt );
        $userExpireDateTimeString = $userExpireDateTime->toString();

        /*
        print_r( "Name: ". $userObjectName . "\n" );
        print_r( "Current Date: ". $currentDateTimeString ."\n" );
        print_r( "User Expire Date: ". $userExpireDate ."\n" );
        print_r( "User Expire eZDateTime: ". $userExpireDateTimeString ."\n" );
        */

        if( $currentDateTime->isGreaterThan( $userExpireDateTime ) == true )
        {
            /*
            print_r("Notice: User membership has expired\n");
            */
            $ret = true;
        }
        else
        {
            /*
            print_r("Notice: User membership has not expired\n");
            */
            $ret = false;
        }

        return $ret;
    }

    /*!
     Check order for subscription product
    */
    function isSubscriptionProduct( $order )
    {
        $ret = false;

        // Settings
        $ini = eZINI::instance( "bcsimplesubscription.ini" );
        $subscriptionProductAttributeName = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionProductAttributeName" );

        // Result storage array of subscriptions
        $subscriptions = array();
        $subscriptionsLength = 0;

        // Check for subscription product purchase
        foreach ( $order->productItems() as $product )
        {
            // Order item
            $collection = $product['item_object'];
            $object = $collection->ContentObject;
            $itemQuantity = $collection->ItemCount;

            if ( $itemQuantity >= 1 )
            {
                // Fetch product number
                $productObjectDatamap = $object->dataMap();

                // Check for subscription product custom attribute
                if ( isset( $productObjectDatamap[$subscriptionProductAttributeName] ) )
                {
                    // Fetch subscription product number
                    $productAttributeNumber = $productObjectDatamap[$subscriptionProductAttributeName];
                    $days = $productAttributeNumber->content();

                    // Product Number Attribute exclusion
                    if ( is_numeric( $days ) && count( $days ) >= 1 && count( $days ) <= 500 )
                    {
                        // order item quantity
                        $quantity = $itemQuantity;

                        // Calculate subscription in days
                        $length = $quantity * $days;

                        $subscriptions[] = array( $length, $quantity, $days );
                        $subscriptionsLength = $length + $subscriptionsLength;
                    }
                }
            }
        }

        if ( count ( $subscriptions ) >= 1 && count ( $subscriptionsLength ) >= 1 )
        {
            $ret = true;
        }

        return $ret;
    }

    /*
      Main method - Activate Subscription
    */
    function activate( $orderID, $currentUser )
    {
        $ret = false;

        // Settings
        $ini = eZINI::instance( "bcsimplesubscription.ini" );

        // Groups IDs
        $subscriptionGroupNodeID = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionGroupNodeID" );
        $subscriptionGuestGroupNodeID = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionGuestGroupNodeID" );

        // Subscription Product Class IDs
        $subscriptionProductClassID = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionProductClassID" );

        // Subscription Attribute Names
        $subscriptionProductAttributeName = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionProductAttributeName" );
        $subscriptionUserAttributeName = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionUserAttributeName" );

        // Fetch order
        $order = eZOrder::fetch( $orderID );

        // Check for subscription product purchase
        if ( $this->isSubscriptionProduct( $order ) == true )
        {
            // Result storage array of subscriptions
            $subscriptions = array();
            $subscriptionsLength = 0;

            // Fetch Order Products :: Fetch Order Subscription Length In Days
            foreach ( $order->productItems() as $product )
            {
                // Order item
                $collection = $product['item_object'];
                $object = $collection->ContentObject;

                // Fetch product number
                $productObjectDatamap = $object->dataMap();

                // Check for subscription product custom attribute
                if ( isset( $productObjectDatamap[$subscriptionProductAttributeName] ) )
                {
                    $productAttributeNumber = $productObjectDatamap[$subscriptionProductAttributeName];

                    // Fetch subscription product number
                    $days = $productAttributeNumber->content();

                    // order item quantity
                    $quantity = $collection->ItemCount;

                    // Calculate subscription in days
                    $length = $quantity * $days;

                    $subscriptions[] = array( $length, $quantity, $days );
                    $subscriptionsLength = $length + $subscriptionsLength;
                }
            }

            if ( count ( $subscriptions ) >= 1 )
            {
                // Fetch current user expiration attribute object
                $expirationDateObjectAttribute = $this->fetchAttribute( $currentUser, $subscriptionUserAttributeName );

                // Current user expiration testing string
                // $currentUserExpireDateDateString = $expirationDateObjectAttribute->toString();

                // Calculate Expiration Date
                $expirationDateObject = $this->newSubscriptionExpirationDate( $subscriptionsLength );

                // Calculated Expiration Testing String
                // $calculatedExpirationDateString = $expirationDateObject->toString();

                // Set User Subscription Attribute Value With Object/Whafever
                $this->setExpirationDate( $currentUser, $expirationDateObject );

                // Upgrade User Membership - Change User Group
                $this->upgradeUserMembership( $currentUser, $subscriptionGroupNodeID );

                // Send Notification? aka Your not a member, here is the direct link to access to content xyz
                // load body template contents into var as proceed plain text output of tpl
                $ret = $this->sendMembershipActivationNoticeToUserEmail( $currentUser );
            }
        }

        return $ret;
    }

    /*!
     Calculate New Subscription Expiration Date
    */
    function newSubscriptionExpirationDate( $days, $date = false )
    {
        $calculatedExpirationDate = new eZDateTime();
        $calculatedExpirationDate->adjustDateTime( false, false, false, false, $days, false );

        return $calculatedExpirationDate;
    }

    /*!
     Updates user content object attribute expire with updated subscription expiration date (ezdatetime)
    */
    function setExpirationDate( $node, $date )
    {
        $ret = false;

        // Settings
        $ini = eZINI::instance( "bcsimplesubscription.ini" );
        $subscriptionUserAttributeName = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionUserAttributeName" );

        // Fetch Object
        $object  = $node->object();
        $id = $object->ID;
        $version = $object->CurrentVersion;

        // Fetch DateStamp String
        $dateStamp = $date->DateTime;

        // $db =& eZDB::instance();
        // $db->begin();

        // Create New Object Version
        $newVersion  = $object->createNewVersion( true );
        $newVersionNumber = $newVersion->Version;
        $newVersionObject = $newVersion->contentObject();
        $newVersionAttributes = $newVersion->contentObjectAttributes();
        $newVersionAttributeExpireDate = false;

        // Fetch Content Object Attribute
        foreach( $newVersionAttributes as $attribute )
        {
            if( $attribute->ContentClassAttributeIdentifier == $subscriptionUserAttributeName )
            {
                $newVersionAttributeExpireDate = $attribute;
            }
            else
            {
                $newVersionAttributeExpireDate = false;
            }
        }

        if( $newVersionAttributeExpireDate != false )
        {
            // $newVersionAttributeExpireDateStamp = $newVersionAttributeExpireDate->DataInt;
            $newVersionAttributeExpireDate->setAttribute('data_int', $dateStamp );
            $newVersionAttributeExpireDate->store();
            $newVersion->setAttribute( 'status', EZ_CONTENT_OBJECT_STATUS_PUBLISHED );
            $newVersion->store();
            $newVersionObject->store();

            // Publish new version of content object (ezuser)
            include_once( 'lib/ezutils/classes/ezoperationhandler.php' );
            $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $id,
                                                                                         'version' => $newVersionNumber ) );
            $ret = $operationResult;
            // $db->commit();
        }

        return $ret;
    }

    /*!
     Upgrade User Membership
    */
    function upgradeUserMembership( $user, $groupID )
    {
        // print_r( "User membership upgraded!\n" );
        return $this->moveUser( $user, $groupID );
    }

    /*!
     Degrade User Membership
    */
    function degradeUserMembership( $user, $groupID )
    {
        print_r( "User membership degraded!\n" );
        return $this->moveUser( $user, $groupID );
    }

    /*!
     Move User
    */
    function moveUser( $user, $groupID )
    {
        return $user->move( $groupID );
    }

    /*!
     Searches all accounts located within a specific node (user group) searching for accounts to degrade group membership
    */
    function degradeExpiredSubscriptionUsers( )
    {
        $ret = false;

        // Settings
        $ini = eZINI::instance( "bcsimplesubscription.ini" );
        $administratorUserID = $ini->variable( "SimpleSubscriptionSettings", "AdministratorUserID" );
        $classID = $ini->variable( "SimpleSubscriptionSettings", "UserClassID" );
        $subscriptionGroupNodeID = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionGroupNodeID" );
        $subscriptionGuestGroupNodeID = $ini->variable( "SimpleSubscriptionSettings", "SubscriptionGuestGroupNodeID" );
        $sendEmail = $ini->variable( "SimpleSubscriptionSettings", "SendSubscriptionExpirationNotificationEmails" ) ? $ini->variable( "SimpleSubscriptionSettings", "SendSubscriptionExpirationNotificationEmails" ) == 'enabled' : false;
        $debug = $ini->hasVariable( "SimpleSubscriptionSettings", "Debug" ) ? $ini->variable( "SimpleSubscriptionSettings", "Debug" ) == 'enabled' : false;

        // Change Script Session User to Privilaged Role User, Admin
        $this->loginDifferentUser( $administratorUserID );

        // $users =& eZContentObjectTreeNode::subTree( array( 'Depth' => 3 ), $parent_node_id );
        $users =& eZContentObjectTreeNode::subTree( array('ClassFilterArray' => array( $classID ),
                                                          'ClassFilterType' => 'include',
                                                          'Depth' => 1,
                                                          'mainNodeOnly' => true ),
                                                          $subscriptionGroupNodeID );

        // Check all users for expired membership
        foreach ( $users as $user )
        {
            if ( $debug == true )
            {
                $results = $this->sendMembershipExpirationNoticeToUserEmail( $user );
                print_r( $results );
                /* include_once( 'extension/ezdbug/autoloads/ezdbug.php' );
                $d = new eZDBugOperators();
                $d->ezdbugDump( $results, 99, true ); */
                die('here');
            }
            if ( $this->isMembershipExpired( $user ) == true )
            {
                $o = $user->object();
                print_r( "Expire user: ". $o->name() ."!\n" );
                $this->degradeUserMembership( $user, $subscriptionGuestGroupNodeID );

                if( $sendEmail == true )
                    $results = $this->sendMembershipExpirationNoticeToUserEmail( $user );

                $ret = $results;
            }
            else
            {
                // print_r( "Backsell offering ...\n" );
                $ret = true;
            }
        }
        return $ret;
    }

    /*!
     Check user expire attribute for expired membership
    */
    function sendMembershipExpirationNoticeToUserEmail( $userObject )
    {
        // Settings
        $ini = eZINI::instance( "site.ini" );
        $siteUrl = $ini->variable( "SiteSettings", "SiteURL" );

        // Fetch Current User Session / Email
        $userID = $userObject->ContentObjectID;
        $user = eZUser::fetch( $userID );
        $userEmail = $user->Email;

        // Fetch Current User Content Object ObjectID / UserID
        $currentUser = eZUser::currentUser();
        $currentUserObjectID = $currentUser->ContentObjectID;
        $currentUserObject = eZContentObjectTreeNode::fetch( eZContentObjectTreeNode::findMainNode( $currentUserObjectID ) );
        $currentUserObjectDataMap = $currentUserObject->dataMap();

        // Send Email to User
        include_once( 'kernel/common/template.php' );
        $tpl = templateInit();

        // Fetch User First Name
        $attributeFirstName = $currentUserObjectDataMap['first_name'];
        $firstName = $attributeFirstName->content();
        $attributeLastName = $currentUserObjectDataMap['last_name'];
        $lastName = $attributeLastName->content();

        // Fetch User Details
        $tpl->setVariable( 'user_first_name', $firstName );
        $tpl->setVariable( 'user_last_name', $lastName );

        // Fetch site hostname
        $tpl->setVariable( 'site_host', $siteUrl );

        $to = $userEmail;
        $subject = "Subscription Expiration Notification";
        $body = $tpl->fetch("design:subscription_expiration_email_notification.tpl");
        $results = $this->sendNotificationEmail( $to, $subject, $body );

        return $results;
    }

    /*!
     Check user expire attribute for expired membership
    */
    function sendMembershipActivationNoticeToUserEmail( $userObject )
    {
        // Settings
        $ini = eZINI::instance( "site.ini" );
        $siteUrl = $ini->variable( "SiteSettings", "SiteURL" );

        // Fetch Current User Session / Email
        $userID = $userObject->ContentObjectID;
        $user = eZUser::fetch( $userID );
        $userEmail = $user->Email;

        // Fetch Current User Content Object ObjectID / UserID
        $currentUser = eZUser::currentUser();
        $currentUserObjectID = $currentUser->ContentObjectID;
        $currentUserObject = eZContentObjectTreeNode::fetch( eZContentObjectTreeNode::findMainNode( $currentUserObjectID ) );
        $currentUserObjectDataMap = $currentUserObject->dataMap();

        // Fetch User First Name
        $attributeFirstName = $currentUserObjectDataMap['first_name'];
        $firstName = $attributeFirstName->content();
        $attributeLastName = $currentUserObjectDataMap['last_name'];
        $lastName = $attributeLastName->content();

        // Send Email to User
        include_once( 'kernel/common/template.php' );
        $tpl = templateInit();

        // Fetch User Details
        $tpl->setVariable( 'user_first_name', $firstName );
        $tpl->setVariable( 'user_last_name', $lastName );

        // Fetch site hostname
        $tpl->setVariable( 'site_host', $siteUrl );

        $to = $userEmail;
        $subject = "Subscription Activation Notification";
        $body = $tpl->fetch("design:subscription_activation_email_notification.tpl");
        $results = $this->sendNotificationEmail( $to, $subject, $body );

        return $results;
    }

    /*!
     Send Notification Email
    */
    function sendNotificationEmail( $to=false, $subject=false, $body=false )
    {
        include_once( 'lib/ezutils/classes/ezmail.php' );
        include_once( 'lib/ezutils/classes/ezmailtransport.php' );

        $mail = new eZMail();
        $mail->setReceiver( $to );
        $mail->setSubject( $subject );
        $mail->setBody( $body );

        // print_r( $mail ); die();
        $mailResult = eZMailTransport::send( $mail );

        return $mailResult;
    }

    /*!
     Fetch Content Object Attribute
    */
    function fetchAttribute( $object, $attribute_name )
    {
        $ret = false;
        // Calculate Expiration
        $objectObject = $object->object();
        $objectDataMap = $objectObject->dataMap();
        $objectAttributeExpire = $objectDataMap["$attribute_name"];
        $objectAttributeExpireContent = $objectAttributeExpire->content();
        $ret = $objectAttributeExpireContent;
        return $ret;
    }


    /*!
     Fetch Content Object Attribute
    */
    function fetchOrderObject( $Email )
    {
        $ret = false;
        $db =& eZDB::instance();

        $Email = urldecode( $Email );
        $Email = $db->escapeString( $Email );

        $orderArray = $db->arrayQuery( "SELECT ezorder.* FROM ezorder
                                            WHERE is_archived='0'
                                              AND is_temporary='0'
                                              AND email='$Email'
                                         ORDER BY order_nr LIMIT 2" );

        $retOrders = array();
        for( $i=0; $i < count( $orderArray ); $i++ )
        {
            $order =& $orderArray[$i];
            $order = new eZOrder( $order );
            $retOrders[] = $order;
        }
        $ret = $retOrders;

        return $ret;
    }

    /*!
     Fetch Latest Orders by UserID
    */
    function fetchOrder( $userID=false, $asObject=true )
    {
        $ret = false;

        // Fetch Current Order ID
        $orders = eZOrder::active( true, 0, 1, 'created', 'desc' );
        $order = $orders[0];
        $orderID = $order->ID;
        $orderUserID = $order->UserID;

        if ( $userID == $orderUserID )
        {
            if ( $asObject )
            {
                $ret=$order;
            }
            else
            {
                $ret=$orderID;
            }
        }

        return $ret;

        /* print_r( $orders );
        die();
        print_r("IDs: $orderID | $userID | $orderUserID");
        print_r('<hr />');
        include_once( 'extension/ezdbug/autoloads/ezdbug.php' );
        $d = new eZDBugOperators();
        $d->ezdbugDump( $orders, 99, true );
        */
    }

    /*!
     Login a different user id (avoid ez permissions issues as anon user)
     From: eZAdmin's Class eZUserAddition::loginDifferentUser( 14 );
    */
    function loginDifferentUser( $user_id ) //, $attributes_to_export, $seperationChar )
    {
        $http =& eZHTTPTool::instance();
        $currentuser =& eZUser::currentUser();
        $user =& eZUser::fetch( $user_id );

        if ($user==null)
            return false;

        //bye old user
        $currentID = $http->sessionVariable( 'eZUserLoggedInID' );
        $http  =& eZHTTPTool::instance();
        $currentuser->logoutCurrent();

        //welcome new user
        $user->loginCurrent();
        $http->setSessionVariable( 'eZUserAdditionOldID', $user_id );

        return true;
    }

    // Variables
    var $UserID;
    var $UserObjectID;
    var $UserNodeID;
    var $OrderUserID;
    var $CurrentUser;

    var $OrderID;
    var $Order;
    var $ProductNumber;
    var $LengthInDays;
    var $Quantity;

}

?>