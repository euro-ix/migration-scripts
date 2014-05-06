#!/usr/local/bin/php
<?php

// all IXPs should have a parent organisation
//
// this script finds all IXPs with no parent
// and creates an organisation for it

require_once 'database.php';

// start our transacton
if( !$mysqli->begin_transaction() )
    die( "Error: could not start transaction" );

// find all ixps without a parent
$ixpsq = $mysqli->query( 'SELECT * FROM ixps WHERE parent_id IS NULL' );

$dt = new DateTime;
$now = $dt->format( 'Y-m-d H:i:s' );

echo "NOTE: All new records can be identified by timestamp $now\n";

$error_count = 0;

while( $ixp = $ixpsq->fetch_object() )
{
    // first, try and extract the url
    $urlq = $mysqli->query( 'SELECT string_value AS url FROM ixp_property_values WHERE ixp_property_id = 8 AND ixp_id = ' . $ixp->id );
    $url = $urlq->fetch_object()->url;

    $res = $mysqli->query( 'INSERT INTO organizations
        ( name, created_at, updated_at, logo_file_name, logo_content_type, logo_file_size, logo_updated_at,
            affiliation, city, country_id, url, contact_user_id, board_contact_user_id )
        VALUES
            (
                "' . $mysqli->real_escape_string( $ixp->full_name ) . '",
                "' . $now . '", "' . $now . '",
                "' . $mysqli->real_escape_string( $ixp->logo_file_name ) . '",
                "' . $mysqli->real_escape_string( $ixp->logo_content_type ) . '",
                "' . $mysqli->real_escape_string( $ixp->logo_file_size ) . '",
                "' . $mysqli->real_escape_string( $ixp->logo_updated_at ) . '",
                "",
                "' . $mysqli->real_escape_string( $ixp->city ) . '",
                ' . $ixp->country_id . ',
                "' . $mysqli->real_escape_string( $url ) . '",
                ' . ( $ixp->contact_user_id ? $ixp->contact_user_id : 'NULL' ) . ',
                ' . ( $ixp->board_contact_user_id ? $ixp->board_contact_user_id : 'NULL' ) . '
            )'
    );

    if( !$res )
    {
        echo "ERROR: could not create organisation for " . $ixp->id . '/' . $ixp->full_name . " - " . $mysqli->error . "\n";
        $error_count++;
        continue;
    }

    $res = $mysqli->query( 'UPDATE ixps SET parent_type = "Organization", parent_id = ' . $mysqli->insert_id
            . ' WHERE id = ' . $ixp->id
    );

    if( !$res )
    {
        echo "ERROR: could not link organisation for " . $ixp->id . '/' . $ixp->full_name . "\n";
        $error_count++;
        continue;
    }

}

if( $error_count == 0 )
    $mysqli->commit();
