<?php
//
// Definition of BCSimpleSubscriptionType class
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

/*! \file bcsimplesubscriptiontype.php
*/

/*!
  \class BCSimpleSubscriptionType bcsimplesubscriptiontype.php
  \brief The class BCSimpleSubscriptionType handles simple subscription activation
*/

include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'kernel/classes/ezcontentobject.php' );
include_once( 'kernel/classes/datatypes/ezuser/ezuser.php' );
include_once( 'kernel/classes/datatypes/ezdatetime/ezdatetimetype.php' );
include_once( 'kernel/classes/ezorder.php' );
include_once( 'extension/bcsimplesubscription/classes/bcsimplesubscription.php' );

define( 'EZ_WORKFLOW_TYPE_BCSIMPLESUBSCRIPTION_ID', 'bcsimplesubscription' );


class BCSimpleSubscriptionType extends eZWorkflowEventType
{
    /*!
     Constructor
    */
    function BCSimpleSubscriptionType()
    {
        $this->eZWorkflowEventType( EZ_WORKFLOW_TYPE_BCSIMPLESUBSCRIPTION_ID, ezi18n( 'kernel/workflow/event', "Subscription Activation" ) );
        $this->setTriggerTypes( array( 'shop' => array( 'checkout' => array ( 'after' ) ) ) );

        // $this->setTriggerTypes( array( 'shop' => array( 'checkout' => array ( 'after' ) ) ) );
        // $this->setTriggerTypes( array( 'shop' => array( 'addtobasket' => array ( 'after' ) ) ) );
    }

    /*!
     Constructor
    */
    function execute( &$process, &$event )
    {
        // Set default return variable
        $ret = false;

        // Create a new object
        $s = new BCSimpleSubscription();

        // Fetch Current ObjectID / UserID
        $currentUser = eZUser::currentUser();
        $currentUserObjectID = $currentUser->ContentObjectID;

        // Fetch Current User Content Object NodeID and Object
        $currentUserNodeID = eZContentObjectTreeNode::findMainNode( $currentUserObjectID );
        $currentUserObject = eZContentObjectTreeNode::fetch( $currentUserNodeID );

        // print_r( $s->fetchOrder( $currentUserObjectID, false ) );
        // $currentUserEmail = $currentUser->Email;
        // $order = $s->fetchOrder( $currentUserObjectID, true );
        // if ( is_object( $order ) )

        if ( is_numeric( $s->fetchOrder( $currentUserObjectID, false ) ) )
        {
            // Fetch Current Order ID
            // $orderID = $s->fetchOrderObject( $currentUserEmail, alse );
            $orderID = $s->fetchOrder( $currentUserObjectID, false );

            // Subscription Activation
            $results = $s->activate( $orderID, $currentUserObject );
            $ret = $results;
            // print_r( $ret );
            // die();
        }
        else
        {
            // print_r( "Fatal Error: Could not fetch current order"); die();
        }

        // Workflow, Process parameters
        // $parameters = $process->attribute( 'parameter_list' );
        // $orderID = $parameters['order_id'];
        // print_r( $process->attribute( 'parameter_list' ) );
        // die( $ret );

        return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerType( EZ_WORKFLOW_TYPE_BCSIMPLESUBSCRIPTION_ID, "bcsimplesubscriptiontype" );

?>