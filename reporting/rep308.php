<?php
/**********************************************************************
    Copyright (C) Grameen Solutions Ltd.(www.grameensolutions.com)
***********************************************************************/
$page_security = 'SA_ITEMSVALREP';
// ----------------------------------------------------------------
// $ Revision:	2.0 $
// Creator:	Jujuk
// date_:	2011-05-24
// Title:	Stock Movements
// ----------------------------------------------------------------
$path_to_root="..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/ui/ui_input.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/sales/includes/db/sales_types_db.inc");
include_once($path_to_root . "/inventory/includes/inventory_db.inc");

//----------------------------------------------------------------------------------------------------

inventory_movements();

function fetch_items($category=0)
{
		$sql = "SELECT stock_id, stock.description AS name,
				stock.category_id,
				units,material_cost,
				cat.description
			FROM ".TB_PREF."stock_master stock LEFT JOIN ".TB_PREF."stock_category cat ON stock.category_id=cat.category_id
				WHERE mb_flag <> 'D'";
		if ($category != 0)
			$sql .= " AND cat.category_id = ".db_escape($category);
		$sql .= " ORDER BY stock.category_id, stock_id";

    return db_query($sql,"No transactions were returned");
}

function trans_qty($stock_id, $location=null, $from_date, $to_date, $inward = true)
{
	if ($from_date == null)
		$from_date = Today();

	$from_date = date2sql($from_date);	

	if ($to_date == null)
		$to_date = Today();

	$to_date = date2sql($to_date);

	$sql = "SELECT ".($inward ? '' : '-')."SUM(qty) FROM ".TB_PREF."stock_moves
		WHERE stock_id=".db_escape($stock_id)."
		AND tran_date >= '$from_date' 
		AND tran_date <= '$to_date'";

	if ($location != '')
		$sql .= " AND loc_code = ".db_escape($location);

	if ($inward)
		$sql .= " AND qty > 0 ";
	else
		$sql .= " AND qty < 0 ";

	$result = db_query($sql, "QOH calculation failed");

	$myrow = db_fetch_row($result);	

	return $myrow[0];

}

//----------------------------------------------------------------------------------------------------

function trans_qty_unit_cost($stock_id, $location=null, $from_date, $to_date, $inward = true)
{
	if ($from_date == null)
		$from_date = Today();

	$from_date = date2sql($from_date);	

	if ($to_date == null)
		$to_date = Today();

	$to_date = date2sql($to_date);

	$sql = "SELECT AVG (price)   FROM ".TB_PREF."stock_moves
		WHERE stock_id=".db_escape($stock_id)."
		AND tran_date >= '$from_date' 
		AND tran_date <= '$to_date'";

	if ($location != '')
		$sql .= " AND loc_code = ".db_escape($location);

	if ($inward)
		$sql .= " AND qty > 0 ";
	else
		$sql .= " AND qty < 0 ";

	$result = db_query($sql, "QOH calculation failed");

	$myrow = db_fetch_row($result);	

	return $myrow[0];

}

//----------------------------------------------------------------------------------------------------

function inventory_movements()
{
    global $path_to_root;

    $from_date = $_POST['PARAM_0'];
    $to_date = $_POST['PARAM_1'];
    $category = $_POST['PARAM_2'];
	$location = $_POST['PARAM_3'];
    $comments = $_POST['PARAM_4'];
	$orientation = $_POST['PARAM_5'];
	$destination = $_POST['PARAM_6'];
	if ($destination)
		include_once($path_to_root . "/reporting/includes/excel_report.inc");
	else
		include_once($path_to_root . "/reporting/includes/pdf_report.inc");

	$orientation = ($orientation ? 'L' : 'P');
	if ($category == ALL_NUMERIC)
		$category = 0;
	if ($category == 0)
		$cat = _('All');
	else
		$cat = get_category_name($category);

	if ($location == '')
		$loc = _('All');
	else
		$loc = get_location_name($location);

	$cols = array(0, 60, 130, 160, 185, 210, 250, 275, 300, 340, 365, 390, 430, 455, 480, 520);

	$headers = array(_('Category'), _('Description'),	_('UOM'), '', '', _('OpeningStock'), '', '',_('StockIn'), '', '', _('Delivery'), '', '', _('ClosingStock'));
	$headers2 = array("", "", "", _("QTY"), _("Rate"), _("Value"), _("QTY"), _("Rate"), _("Value"), _("QTY"), _("Rate"), _("Value"), _("QTY"), _("Rate"), _("Value"));

	$aligns = array('left',	'left',	'left', 'right', 'right', 'right', 'right','right' ,'right', 'right', 'right','right', 'right', 'right', 'right');

    $params =   array( 	0 => $comments,
						1 => array('text' => _('Period'), 'from' => $from_date, 'to' => $to_date),
    				    2 => array('text' => _('Category'), 'from' => $cat, 'to' => ''),
						3 => array('text' => _('Location'), 'from' => $loc, 'to' => ''));

    $rep = new FrontReport(_('Costed Inventory Movements'), "CostedInventoryMovements", user_pagesize(), 8, $orientation);
    if ($orientation == 'L')
    	recalculate_cols($cols);

    $rep->Font();
    $rep->Info($params, $cols, $headers2, $aligns, $cols, $headers, $aligns);
    $rep->NewPage();

	$result = fetch_items($category);

	$catgor = '';
	while ($myrow=db_fetch($result))
	{
		if ($catgor != $myrow['description'])
		{
			$rep->NewLine(2);
			$rep->fontSize += 2;
			$rep->TextCol(0, 3, $myrow['category_id'] . " - " . $myrow['description']);
			$catgor = $myrow['description'];
			$rep->fontSize -= 2;
			$rep->NewLine();
		}
		$rep->NewLine();
		$rep->TextCol(0, 1,	$myrow['stock_id']);
		$rep->TextCol(1, 2, $myrow['name']);
		$rep->TextCol(2, 3, $myrow['units']);
		$qoh_start= $inward = $outward = $qoh_end = 0; 
		
		$qoh_start += get_qoh_on_date($myrow['stock_id'], $location, add_days($from_date, -1));
		$qoh_end += get_qoh_on_date($myrow['stock_id'], $location, $to_date);
		
		$inward += trans_qty($myrow['stock_id'], $location, $from_date, $to_date);
		$outward += trans_qty($myrow['stock_id'], $location, $from_date, $to_date, false);
		$unitCost=$myrow['material_cost'];
		$rep->AmountCol(3, 4, $qoh_start, get_qty_dec($myrow['stock_id']));
//		$rep->AmountCol(4, 5, $unitCost, get_qty_dec($myrow['stock_id']));
		$rep->AmountCol(4, 5, $myrow['material_cost']);
		$rep->AmountCol(5, 6, $qoh_start*$unitCost, get_qty_dec($myrow['stock_id']));
		
		if($inward>0){
			$rep->AmountCol(6, 7, $inward, get_qty_dec($myrow['stock_id']));
			$unitCost_IN=	trans_qty_unit_cost($myrow['stock_id'], $location, $from_date, $to_date);
			$rep->AmountCol(7, 8, $unitCost_IN,get_qty_dec($myrow['stock_id']));
			$rep->AmountCol(8, 9, $inward*$unitCost_IN, get_qty_dec($myrow['stock_id']));
		}
		
		if($outward>0){
			$rep->AmountCol(9, 10, $outward, get_qty_dec($myrow['stock_id']));
		
			$unitCost_out=	trans_qty_unit_cost($myrow['stock_id'], $location, $from_date, $to_date, false);
			$rep->AmountCol(10, 11, $unitCost_out,get_qty_dec($myrow['stock_id']));
			$rep->AmountCol(11, 12, $outward*$unitCost_out, get_qty_dec($myrow['stock_id']));
		}
		
		$rep->AmountCol(12, 13, $qoh_end, get_qty_dec($myrow['stock_id']));
		$rep->AmountCol(13, 14, $myrow['material_cost'],get_qty_dec($myrow['stock_id']));
		$rep->AmountCol(14, 15, $qoh_end*$unitCost, get_qty_dec($myrow['stock_id']));
		
		$rep->NewLine(0, 1);
	}
	$rep->Line($rep->row  - 4);

	$rep->NewLine();
    $rep->End();
}

?>