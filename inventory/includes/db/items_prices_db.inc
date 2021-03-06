<?php
/**********************************************************************
    Copyright (C) Grameen Solutions Ltd.(www.grameensolutions.com)
***********************************************************************/
function add_item_price($stock_id, $sales_type_id, $curr_abrev, $price)
{
	$sql = "INSERT INTO ".TB_PREF."prices (stock_id, sales_type_id, curr_abrev, price) 
		VALUES (".db_escape($stock_id).", ".db_escape($sales_type_id)
		.", ".db_escape($curr_abrev).", ".db_escape($price).")";
	
	db_query($sql,"an item price could not be added");		
}

function update_item_price($price_id, $sales_type_id, $curr_abrev, $price)
{
	$sql = "UPDATE ".TB_PREF."prices SET sales_type_id=".db_escape($sales_type_id).", 
		curr_abrev=".db_escape($curr_abrev).", 
		price=".db_escape($price)." WHERE id=".db_escape($price_id);
	
	db_query($sql,"an item price could not be updated");		
}

function delete_item_price($price_id)
{
	$sql="DELETE FROM ".TB_PREF."prices WHERE id= ".db_escape($price_id);
	db_query($sql,"an item price could not be deleted");			
}

function get_prices($stock_id)
{
	$sql = "SELECT ".TB_PREF."sales_types.sales_type, ".TB_PREF."prices.* 
		FROM ".TB_PREF."prices, ".TB_PREF."sales_types 
		WHERE ".TB_PREF."prices.sales_type_id = ".TB_PREF."sales_types.id 
		AND stock_id=".db_escape($stock_id)
		." ORDER BY curr_abrev, sales_type_id";	
	
	return db_query($sql,"item prices could not be retreived");
}

function get_stock_price($price_id)
{
	$sql = "SELECT * FROM ".TB_PREF."prices WHERE id=".db_escape($price_id);
	
	$result = db_query($sql,"price could not be retreived");
	
	return db_fetch($result);
}

function get_stock_price_type_currency($stock_id, $type, $currency)
{
	$sql = "SELECT * FROM ".TB_PREF."prices WHERE stock_id=".db_escape($stock_id)."
		AND sales_type_id=".db_escape($type)."
		AND curr_abrev=".db_escape($currency);
	
	$result = db_query($sql,"price could not be retreived");
	
	return db_fetch($result);
}
?>