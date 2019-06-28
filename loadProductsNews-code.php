<?php
//set_time_limit(0);//RM#13978
//ini_set('memory_limit','-1');//RM#13978

//RM#8300
global $stforecastorderpoints;
if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
{
if(isset($_REQUEST['cronfile']) && $_REQUEST['cronfile']=='1')
{
	chdir('../..');
  }
}

require_once('include/utils/utils.php');
include_once('config.php');
require_once('include/logging.php'); 
require_once('include/utils/productDetailUtils.php');

global $adb, $adbRead;

set_time_limit(0);	 //RM#13978
ini_set('memory_limit','-1');  //RM#13978

ob_implicit_flush(TRUE);	 //RM#13978
ob_end_flush();			 //RM#13978
$STtype = trim($_REQUEST['sttype']);
$tolocation = trim($_REQUEST['tolocation']);
$flocation = trim($_REQUEST['flocation']);

//RM#8300 - start
if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
{
$lineCodes = trim($_REQUEST['linecodes']); 
}
else
{  
  $lineCodes = trim($_REQUEST['selectedLCode']);
}
//RM#8300 - end

$stBlanaceFromLoc = trim($_REQUEST['stBalanceQtyReq']);
$includeConsignment = trim($_REQUEST['include_consignment']); //RM-2123
$consider_reorder = trim($_REQUEST['consider_reorder']); //RM#2638
$alt_part_ordering = trim($_REQUEST['alt_part_ordering']); //RM#6442
$TAconditionAlt = '';//RM#13978 ST Template Failing fixed
if(isset($_REQUEST['cronfile']) && $_REQUEST['cronfile']=='1' && $alt_part_ordering == true) /* RM#13978 */   
{
  $TAconditionAlt = " AND (lc.cf_844 > 0 || lc.cf_1626 !='')"; // no need its bug  RM#14110 
}
$mainLocation = getLocationNameByID($flocation);
$stnumber = trim($_REQUEST['stnumber']);/* RM#2680 */
$responseData = array('data'=>'', 'message'=>'');
$st_reorder_to = trim($_REQUEST['st_reorder_to']); //RM#7455

if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))//RM#8300
{
$productFld = 'cf_898';
if($STtype == "ORDER TO MIN" || $STtype == "RETURN ORDER TO MIN")
{
	  $productFld = 'cf_898';
}	  
else if($STtype == "ORDER TO MAX" || $STtype == "RETURN ORDER TO MAX")
{
	  $productFld = 'cf_900';
}	  
else if($STtype == "ORDER TO ORDER POINT" || $STtype == "RETURN ORDER TO ORDER POINT")
{
	  $productFld = 'cf_902';
  }
  else if($STtype == "HIGHEST ORDER POINTS") //RM#8501
  {
    $productFld = 'highest_order_point';
}
}
else
{
  $productFld = 'cf_898';
  if($_REQUEST['minSTOQBasedON'] == "o2min")
  {  
    $productFld = 'cf_898';
  }    
  else if($_REQUEST['minSTOQBasedON'] == "o2max")
  {  
    $productFld = 'cf_900' ;
  }     
  else if($_REQUEST['minSTOQBasedON'] == "o2ptp")
  {  
    $productFld = 'cf_902';
  }     
}  

$extCond = "";

//RM#2638 - start
if($consider_reorder != 'true')
{
$reorderCond = " AND cf_1354 = 'Yes' "; //RM# 3368
}
//RM#2638 - end

$streordertofld = 'cf_898';
if(!empty($st_reorder_to) && $st_reorder_to == "ORDER TO MIN")
{
  $streordertofld = 'cf_898';
}	  
else if(!empty($st_reorder_to) && $st_reorder_to == "ORDER TO MAX")
{
  $streordertofld = 'cf_900';
}	  
else if(!empty($st_reorder_to) && $st_reorder_to == "ORDER TO ORDER POINT")
{
  $streordertofld = 'cf_902';
}
$locId = $tolocation;
if(!preg_match("|RETURN|im", $STtype)) // Normal O2X
{
  $st_highest_ordpt_field = array();//RM#8501
  if(!empty($st_reorder_to) && $st_reorder_to != '' && ($STtype != $st_reorder_to))
  {
    $extCond = " AND ($streordertofld - cf_844 + cf_846) >= cf_1067 AND cf_892 <> 'Yes' ";
  }
  else if($productFld == 'highest_order_point') //RM#8501
  {
    if(isset($_REQUEST['st_highest_ordpt_pmin']) and $_REQUEST['st_highest_ordpt_pmin'] !='') 
    {
      $st_highest_ordpt_field[] = $_REQUEST['st_highest_ordpt_pmin']; 
    }
    if(isset($_REQUEST['st_highest_ordpt_pmax']) and $_REQUEST['st_highest_ordpt_pmax'] !='') 
    {
      $st_highest_ordpt_field[] = $_REQUEST['st_highest_ordpt_pmax']; 
    }
    if(isset($_REQUEST['st_highest_ordpt_p2p']) and $_REQUEST['st_highest_ordpt_p2p'] !='') 
    {
      $st_highest_ordpt_field[] = $_REQUEST['st_highest_ordpt_p2p']; 
    }
    $extCond = " AND cf_892 <> 'Yes' ";
  }
  else
  {
	$extCond = " AND ($productFld - cf_844 + cf_846) > cf_1067 AND cf_892 <> 'Yes' ";
}
}
else if ($STtype == 'RETURN TO ZERO') // RM-1881
{
	$productFld = 'cf_840';
	$locId = $flocation;
	$extCond = " AND cf_840 > 0 ";
    $reorderCond = ""; //RM# 3368 for ST type "return to zero" do not check Reorder = yes/no
}
else // Return O2X
{
	$locId = $flocation;
	$extCond = " AND (cf_844 - $productFld) > 0 ";
}

//RM#5805 - New ST Increment Functionality  /* RM#6127 */
/* RM#6127 */
$st_incremental_field = array();
if(isset($_REQUEST['st_incremental_BQ']) and $_REQUEST['st_incremental_BQ'] !='') 
{
  $st_incremental_field[] = $_REQUEST['st_incremental_BQ']; 
}
if(isset($_REQUEST['st_incremental_PI']) and $_REQUEST['st_incremental_PI'] !='') 
{
  $st_incremental_field[] = $_REQUEST['st_incremental_PI']; 
}
if(isset($_REQUEST['st_incremental_MOQ']) and $_REQUEST['st_incremental_MOQ'] !='') 
{
  $st_incremental_field[] = $_REQUEST['st_incremental_MOQ']; 
}
//RM#6371 pri
if(isset($_REQUEST['st_incremental_PCQ']) and $_REQUEST['st_incremental_PCQ'] !='') 
{
  $st_incremental_field[] = $_REQUEST['st_incremental_PCQ']; 
}
$st_incremental_loc = $_REQUEST['st_incremental_loc'];
$st_incremental_field = implode(',',$st_incremental_field);
 
//RM#3049;
/*RM#10026: Remove "'Discontinued'" from where condition.*/
$extCond .=" AND pc.part_status NOT IN ('Inactive') ";
//RM # 4064 -added cf_778, cf_784
//RM#12075 remove commented code
//RM#5686
$LineCodeSubLine = "";
$linecodeArr = explode(",",$lineCodes);
for($i=0;$i<count($linecodeArr);$i++)
{
	$subline = explode(":",$linecodeArr[$i]);
	if(count($subline)==2)
	{
		if($LineCodeSubLine!="")
    {
      //RM#8300
      if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
      {
			$LineCodeSubLine .= " OR ( cf_836 = ".$subline[0]."' AND cf_780 = '".$subline[1]." ) ";
      }
      else
      {
        $LineCodeSubLine .= " OR ( cf_836 = '".$subline[0]."' AND cf_780 = '".$subline[1]."' ) ";
      }        
    }			
		else
    {
      //RM#8300
      if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
      {
			$LineCodeSubLine = " ( cf_836 = ".$subline[0]."' AND cf_780 = '".$subline[1]." ) ";
    }
      else
      {
        $LineCodeSubLine = " ( cf_836 = 1".$subline[0]."' AND cf_780 = '".$subline[1]."1 ) ";
      }        
    }			
	}
	else
	{
		if($LineCodeSubLine!="")
    {
      //RM#8300
      if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
      {
			$LineCodeSubLine .=" OR ( cf_836 = ".$linecodeArr[$i]." )";
      }  
      else
      {
        $LineCodeSubLine .=" OR ( cf_836 = '".$linecodeArr[$i]."' )";
      }
    }			
		else
    {
      //RM#8300
      if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
      {
			$LineCodeSubLine = " ( cf_836 = ".$linecodeArr[$i]." ) ";
    }
      else
      {
        $LineCodeSubLine = " ( cf_836 = '".$linecodeArr[$i]."' ) ";
  }
}
	}
}

//echo '<br> LineCodeSubLine : ';
//pr($LineCodeSubLine,false);
/* RM#13879 */
//$TEMPORARY_TABLE = "a_TEMPORARY_stloadparts_".uniqid() ;// without order by 
//$SQL_ORDERBY     = " order by cf_836, cf_838 ";
//RM#8300 - start
if(!isset($stforecastorderpoints) || empty($stforecastorderpoints))
{
  //RM#10026: Add part_status to select;

   //  order by RM#13879 
   $sql = "SELECT cf_1472, cf_844, cf_840, cf_898, cf_900, cf_902, cf_846, cf_1067, cf_892, cf_836, cf_838, cf_1708, cf_916, cf_918, cf_1356, cf_778, cf_784, cf_1868,cf_856, part_status
        FROM vtiger_locationcf as lc 
        inner join vtiger_productcf as pc on lc.cf_1472 = pc.productid
        WHERE pc.deleted = '0' and ( ".$LineCodeSubLine." ) $reorderCond $extCond $TAconditionAlt AND locationid = '$locId'
        ";
    $result = $adbRead->pquery($sql,array()); //RM#12075 //RM#13978 ADDED $TAconditionAlt removed order by cf_836, cf_838
     

  /*$sql = "SELECT cf_1472, cf_844, cf_840, cf_898, cf_900, cf_902, cf_846, cf_1067, cf_892, cf_836, cf_838, cf_1708, cf_916, cf_918, cf_1356, cf_778, cf_784, cf_1868,cf_856, part_status
        FROM vtiger_locationcf as lc
        inner join vtiger_products as p on lc.cf_1472 = p.productid
        inner join vtiger_productcf as pc on lc.cf_1472 = pc.productid
        WHERE p.deleted = '0' and ( ".$LineCodeSubLine." ) $reorderCond $extCond AND locationid = '$locId' order by cf_836, cf_838";//RM#12075
  //echo $sql;exit;
   $result = $adbRead->pquery($sql,array());*/

}
else
{
  //RM#10026: Add part_status to select;
  $createtemptable = "CREATE TABLE a_stforecastwithorderpoint$currTime AS 
                    (
                      SELECT cf_1472, cf_844, cf_840, cf_898, cf_900,    cf_902, cf_846, cf_1067, cf_892, cf_836, cf_838, cf_1708, cf_916, cf_918, cf_1356, cf_778, cf_784, cf_1868,cf_856,part_status
                      FROM vtiger_locationcf as lc
                      inner join vtiger_products as p on lc.cf_1472 = p.productid
                      inner join vtiger_productcf as pc on lc.cf_1472 = pc.productid
                      WHERE p.deleted = '0' and ( ".$LineCodeSubLine." ) $reorderCond $extCond $TAconditionAlt AND locationid = '$locId'  
                    )";//RM#12075 //RM#13978 ADDED $TAconditionAlt removed order by cf_836, cf_838
  $adb->pquery($createtemptable,array());
  unset($createtemptable);
  if($_REQUEST['sttype'] == "FORECAST") // Bypassing O2X process when we press GET ALL product and po type = FORECAST
  {
      echo $currTime;
      exit;
  }
  $sql = "SELECT * FROM a_stforecastwithorderpoint$currTime";    
  $result = $adb->pquery($sql,array());
}  
//RM#8300 - end
//fp($sql);


if($adb->num_rows($result) > 0)
{
	 /* Remove existing date , for the case user request again */
	if($stnumber)
	{ 
		$sql = "DELETE FROM fuse5_storetransferiframedata 
				WHERE fuse5_storetransferiframedata.stnumber = ?";
		$savearray = array($stnumber);
		$adb->pquery($sql,$savearray); 
	}	
	$cnt = 1;
  
  //pr($arrProdPOQoO,false);
  while($row = $adb->fetchByAssoc($result))
     {
		$product = 0;
    
    /*RM#10026: Start;*/
    if($STtype != "ORDER TO MIN" && $STtype != "ORDER TO MAX" && $STtype != "ORDER TO ORDER POINT")
    {
      if((int)$row['cf_840'] <= 0 && $row['part_status'] == 'Discontinued')
      {
        continue;
      }  
    }          
    /*RM#10026: End;*/
    
			if(!preg_match("|RETURN|im", $STtype)) // Normal O2X
			{
      //RM#8501 - start
      if(!empty($st_highest_ordpt_field) && count($st_highest_ordpt_field) > 0)
      {
        $tmpordptarr = array();
        foreach($st_highest_ordpt_field as $kk => $vv)
        {
          $tmpordptarr[$vv] = $row[$vv];
        }
        $productFld = array_search(max($tmpordptarr),$tmpordptarr);
      }
      //RM#8501 - end
      //RM#7840,//RM#7933 - start
				$product = $row[$productFld];
      /*if(!empty($st_reorder_to) && $st_reorder_to != '' && ($STtype != $st_reorder_to))
      {
        $product = $row[$streordertofld];
      }*/
      //RM#7840,//RM#7933 - end
				// RM-2123
        if( (isset($includeConsignment) && $includeConsignment == 'true') && (($STtype == "ORDER TO MIN" || $STtype == "ORDER TO MAX" || $STtype == "ORDER TO ORDER POINT") && $STtype != 'FORECAST' ))
        {
          $totalQoH = $row['cf_840'] + $row['cf_1708'];
        }          
        else
        {
          $totalQoH = $row['cf_840'];
        }          

        // #1233 - if QoH<0, order off 0 - Dip
        /*if( $totalQoH < 0 ) //Commenting for #7519
        {
            $totalQoH = 0;
        }*/
        // RM-2123

				$TOQoO = $row['cf_1067'];
				
      //RM#7455 - start
      if(!empty($st_reorder_to) && $st_reorder_to != '')
      {
        if($st_reorder_to == "ORDER TO MIN")
          $totalStReorder_to = $row['cf_898'];
        else if($st_reorder_to == "ORDER TO MAX")
          $totalStReorder_to = $row['cf_900'];
        else if($st_reorder_to == "ORDER TO ORDER POINT")
          $totalStReorder_to = $row['cf_902'];
      }
      //RM#7455 - end
        
        //                    QOH    +  QoO   -  CUST BO       - OSTQ
        $calculatedVal  = ($totalQoH + $TOQoO - $row['cf_846'] - $row['cf_1868']);/*RM # 5312*/

        if((!empty($st_reorder_to) && $st_reorder_to != '' && ($STtype != $st_reorder_to)) ? ($calculatedVal <= $product) : ($calculatedVal < $product)) // QUANTITY ON ORDER  //RM#9345 - alt part orderning
				{
					$tempQty = $product - $calculatedVal;

					/* removed fixed RM#6127
					if($tempQty < $row['cf_916'])
						$qty = $row['cf_916'];
					else
						$qty = $tempQty;
					*/
					$qty = $tempQty;  

					/* 
					 removed fixed RM#6127
					if($qty < $row['cf_918'])
						$finalQty = $row['cf_918'];
					else
						$finalQty = $qty;
					*/
          $finalQty = $qty;
	  
          //RM#7254 - start
          if(!empty($st_reorder_to) && $st_reorder_to != '')
          {
	    if($calculatedVal <= $totalStReorder_to)
            { 
              $finalQty = $totalStReorder_to - $calculatedVal;
            }
          }		
          //RM#7254 - end
          
          /*RM # 4064 -start*/
          if($STtype == "ORDER TO MIN" || $STtype == "ORDER TO MAX" || $STtype == "ORDER TO ORDER POINT")
          {
            $row['cf_778'] = trim($row['cf_778']);
            $row['cf_784'] = trim($row['cf_784']);

            if(!empty($row['cf_778']) && !empty($row['cf_784']))
            {
                $qohOfSupercededPart = checkPartIsSupercedeOfOtherPart($row['cf_778'], $row['cf_784'], $locId);

                if($qohOfSupercededPart != 0)
                {
                  if( $qohOfSupercededPart < $finalQty )
                  {   
                    $finalQty = $finalQty - $qohOfSupercededPart;
                  }                        
                  else
                  {
                    continue;
                  }                        
                }
            }  
          } //RM # 4064 -end
          
        //RM#11315 - start
        //RM#8426 //RM#10776
          $sql = "SELECT cf_778, cf_782, cf_784, cf_836,cf_838,cf_840,cf_844,cf_898,cf_900,cf_902,cf_1356,cf_918,cf_916,cf_856, supercededallowtransfer
                          FROM vtiger_locationcf as lc
									INNER JOIN vtiger_productcf as pc on lc.cf_1472 = pc.productid
                      WHERE pc.deleted = '0' AND lc.cf_1472 = '".$row['cf_1472']."' AND locationid = '".$flocation."' ";//RM#12075 //  RM#13879 removed order by cf_836, cf_838
          $resultFromLoc = $adbRead->pquery($sql,array());
        
        $protect_inventory = '';
        if(($stBlanaceFromLoc == 'PMIN' || $stBlanaceFromLoc == 'PMAX' || $stBlanaceFromLoc == 'POP') && $STtype != 'FORECAST' && $STtype != 'SPECIAL BUY')      
        {
          $protect_inventory = $stBlanaceFromLoc;
        }
        
        //RM#10776 - start
        $fromLineCode = $adb->query_result($resultFromLoc,0,'cf_778');
        $fromProductNumber = $adb->query_result($resultFromLoc,0,'cf_782');
        $fromProductStripped = $adb->query_result($resultFromLoc,0,'cf_784');
        $supercededProducts = array();
        getSupercededProductsForST($supercededProducts, $finalQty, $fromLineCode, $fromProductStripped, $flocation, $tolocation, $protect_inventory);
        foreach ($supercededProducts as $singleProduct)
        {
          $productArray[$singleProduct['cf_1472']] = $singleProduct['cf_1472'].'##'.$singleProduct['transferQty'].'##'.$STtype.'##'.$st_reorder_to;
          $cnt++;
        }
        //RM#10776 - end
        //RM#11315 - end
        
        // RM-1881
        if(($stBlanaceFromLoc == 'PMIN' || $stBlanaceFromLoc == 'PMAX' || $stBlanaceFromLoc == 'POP') && $STtype != 'FORECAST' && $STtype != 'SPECIAL BUY')
        { 
          
          //RM#11315 - Query execution moved upword

	                    $fromTA = $adb->query_result($resultFromLoc,0,'cf_844');
	                    $fromPMin = $adb->query_result($resultFromLoc,0,'cf_898');
	                    $fromPMax = $adb->query_result($resultFromLoc,0,'cf_900');
	                    $fromPop = $adb->query_result($resultFromLoc,0,'cf_902');
            $fromPI = $adb->query_result($resultFromLoc,0,'cf_1356');//RM#5805
            $fromBQ = $adb->query_result($resultFromLoc,0,'cf_918');//RM#5805
            $fromMOQ = $adb->query_result($resultFromLoc,0,'cf_916');//RM#6127
          $fromPCQ = $adb->query_result($resultFromLoc,0,'cf_856');//RM#6371 pri  
          $fromQoH = $adb->query_result($resultFromLoc,0,'cf_840');//RM#10026
          //RM#10026: Add if condition;
          if($fromQoH <= 0 && $row['part_status'] == 'Discontinued')
          {
            continue;
          }

	                    if($stBlanaceFromLoc == 'PMIN')
          {
	                    	$finalTrnsferQty = $fromTA - $fromPMin;
            $fromlocinventory = $fromPMin; //RM#8187
          }              
	                    elseif($stBlanaceFromLoc == 'PMAX')
          {
	                    	$finalTrnsferQty = $fromTA - $fromPMax;
            $fromlocinventory = $fromPMax; //RM#8187
          }              
	                    elseif($stBlanaceFromLoc == 'POP')
          {
	                    	$finalTrnsferQty = $fromTA - $fromPop;
            $fromlocinventory = $fromPop; //RM#8187
          }              

		
            /* Apply Round Up Options */
						//RM#5805 - start /* RM#6127 added st_incremental_loc */
          //RM#6371 pri
          if($st_incremental_loc == 'sttoloc')
          {
            $finalQty = set_st_incremental_field($row['cf_1356'],$row['cf_918'],$row['cf_916'],$row['cf_856'],$finalQty,$st_incremental_field,$row['cf_840'],$TOQoO,$fromTA,$fromlocinventory,$finalTrnsferQty); //RM#8426
						  }
          elseif($st_incremental_loc == 'stfromloc')
          {
            $finalQty = set_st_incremental_field($fromPI,$fromBQ,$fromMOQ,$fromPCQ,$finalQty,$st_incremental_field,$row['cf_840'],$TOQoO,$fromTA,$fromlocinventory,$finalTrnsferQty); //RM#8426
						  }
	    
          //RM#8187
          if($finalTrnsferQty < $finalQty && $fromlocinventory > 0)
            {
            $finalQty = $finalTrnsferQty < 0 ? 0 : $finalTrnsferQty;
            }
      	}
      else
      {
          //RM#11315 - Query execution moved upword
          
          $fromTA = $adb->query_result($resultFromLoc,0,'cf_844');
          $productFromFld = $adb->query_result($resultFromLoc,0,$productFld); //RM#9886
          $fromQoH = $adb->query_result($resultFromLoc,0,'cf_840');//RM#10026
          //RM#10026: Add if condition;
          if($fromQoH <= 0 && $row['part_status'] == 'Discontinued')
          {
            continue;
          }
          $finalTrnsferQty = $fromTA - $productFromFld; //RM#9886
						//RM#5805 - start /* RM#6127 added st_incremental_loc */
          //RM#6371 pri
          if($st_incremental_loc == 'sttoloc')
          {
            $finalQty = set_st_incremental_field($row['cf_1356'],$row['cf_918'],$row['cf_916'],$row['cf_856'],$finalQty,$st_incremental_field,$row['cf_840'],$TOQoO,$fromTA,$productFromFld,$finalTrnsferQty); //RM#8426, //RM#9886
						  }
          elseif($st_incremental_loc == 'stfromloc')
          {
						//RM#5805 - start
            //RM#6371 pri //RM#10776
            /* RM#13879 REMOVED THIS SQL as duplicate $sqlFromLoc = "SELECT cf_778, cf_782, cf_784, cf_1356,cf_918,cf_916,cf_856
									  FROM vtiger_locationcf as lc
									  INNER JOIN vtiger_products as p on lc.cf_1472 = p.productid
									  INNER JOIN vtiger_productcf as pc on lc.cf_1472 = pc.productid
                          WHERE p.deleted = '0' and ( ".$LineCodeSubLine." )
                          AND lc.cf_1472 = '".$row['cf_1472']."' AND locationid = '".$flocation."' order by cf_836, cf_838 "; */
                          //RM#5686 //RM#12075  
				//echo "(3) . ".$sqlFromLoc;		
            /*$resultFromLoc = $adbRead->pquery($sqlFromLoc,array());  */                       
						$fromPI = $adb->query_result($resultFromLoc,0,'cf_1356');
						$fromBQ = $adb->query_result($resultFromLoc,0,'cf_918');
						$fromMOQ = $adb->query_result($resultFromLoc,0,'cf_916');
            $fromPCQ = $adb->query_result($resultFromLoc,0,'cf_856'); //RM#6371 pri
					
            $finalQty = set_st_incremental_field($fromPI,$fromBQ,$fromMOQ,$fromPCQ,$finalQty,$st_incremental_field,$row['cf_840'],$TOQoO,$fromTA,$productFromFld,$finalTrnsferQty); //RM#8426, //RM#9886
						//RM#5805 - end
						          
          }
        }          
					// End

        //RM#11315 - code of RM#10776 moved 
        
					// for Sending Response Text to add product row function
        $productArray[$row['cf_1472']] = $row['cf_1472'].'##'.$finalQty.'##'.$STtype.'##'.$st_reorder_to; //RM#7590,/RM#7680
					$cnt++;
				}
			}
    		else if ($STtype == 'RETURN TO ZERO') // RM-1881
            {
				$trdIf = $row[$productFld] ;
				if($trdIf > 0)
				{
					$tempQty = $trdIf;

					// for Sending Response Text to add product row function
					$productArray[$row['cf_1472']] = $row['cf_1472'].'##'.$tempQty;
					$cnt++;
				}
			}
			else  // Return O2X
			{
				$trdIf = $row['cf_844'] - $row[$productFld] ;     // TA - MIN/MAX/POINT
				if($trdIf > 0) // TA - MIN/MAX/POINT > 0
				{
					$tempQty = $trdIf; //TA - MIN/MAX/POINT

					// for Sending Response Text to add product row function
					$productArray[$row['cf_1472']] = $row['cf_1472'].'##'.$tempQty;
					$cnt++;
				}
			}
	 
	 unset($row);	  //RM#13978
	 } // END of while 
	 unset($result); //RM#13978 
	if(count($productArray) > 0)
  {
		$resultCheck = loadProdcutsinsertIframeStTable($productArray,$alt_part_ordering,$stBlanaceFromLoc); //RM#6442, //RM#8300
		//RM 3045
		if($resultCheck != "NODATA")
		{
			$responseData['data']='Loaded line items in temp table';
			$responseData['message'] = 'ShowIframe';
		}
		else
		{
			 $responseData['message'] = 'hideIframe';
		}
	}
	unset($row);//RM#13978
	unset($productArray); //RM#13978
}
else
{
    $responseData['message'] = 'No Data Found'; 
}
//RM # 4064
function checkPartIsSupercedeOfOtherPart($lineCode, $productNumber, $tolocation)
{
  global $adb, $adbRead;
    $supercededProductQoH = 0;

  $qryChkPartSupercede = "SELECT vtiger_locationcf.cf_840 FROM vtiger_locationcf 
  INNER JOIN vtiger_productcf ON vtiger_productcf.`productid` = vtiger_locationcf.cf_1472
  WHERE vtiger_productcf.deleted = 0 AND vtiger_locationcf.cf_892 = 'Yes' AND vtiger_locationcf.cf_894 = '$lineCode' AND vtiger_locationcf.cf_896 = '$productNumber' AND vtiger_locationcf.locationid in ($tolocation)";
  /*RM#14058*/
//echo "(checkPartIsSupercedeOfOtherPart) . ".$qryChkPartSupercede;		
  $lineProductRes = $adbRead->pquery($qryChkPartSupercede,array());

    if($adb->num_rows($lineProductRes) > 0)
    {
    while($rowParentQoH = $adb->fetchByAssoc($lineProductRes))
        {
            $supercededProductQoH += $rowParentQoH['cf_840'];
        }
    }

	return $supercededProductQoH;
}

/* RM#5805 Round UP Options  
* Buy Qty
* Purchase Increment
* Min Ord Qty
* Per Car Qty
__________________________________________________________________________________
# Logic --> PCQ & another purchase increment (Buy Qty & Purchase Increment)#
----------------------------------------------------------------------------------
>Rule 1, Final <Actual Transfer Qty> must be a multiple of PI, (if only PI round up option are selected).
>Rule 2, Final <Actual Transfer Qty> must be a multiple of PCQ,(if only PCQ round up option are selected).
>Rule 3, If PI > PCQ, (if both PI,PCQ round up options are selected) then <Actual Transfer Qty> must be a multiple of PI in all cases PI selected ,So use ONLY PI hence apply Rule#1 
>Rule 4, If PI < PCQ, (if both PI,PCQ round up options are selected) then <Actual Transfer Qty> must be a multiple of PI in all cases PI selected ,So use ONLY PI hence apply Rule#1 
Dave: Ok. Implement however you want Pri.  
Just make sure of two things; 
##1) If PI > PCQ (and both round up options are selected), ignore PCQ.  
##2) <Actual Transfer Qty> must be a multiple of PI in all cases when PI is selected
I don't like PCQ as a rounding option.  It is just a bad idea.
It SHOULD be managed through O2X.
IE O2X must be a multiple of PCQ.
I'm not trying to be difficult here but this is the thing.
PCQ by itself is not commonly used.
//RM#8429 - ROund up formula
finalqty = ceil((TA-Protected Qty)/BQ)* BQ
*/
//RM#8426 - $fromTA, $fromlocinventory

//RM#6819 - moved function in commonUtils.php    
//RM#5805 - end

//$responseData['TEMPORARY_TABLE'] = $TEMPORARY_TABLE; // RM#13879
//$sqlTemp = "DROP TEMPORARY TABLE $TEMPORARY_TABLE"; //RM#13879 
//$adb->pquery($sqlTemp,array());  //  RM#13879   // RM#13879 
if(isset($stforecastorderpoints) && $stforecastorderpoints == "yes") //RM#8300
{
   $sql = "DROP TABLE IF EXISTS a_stforecastwithorderpoint$currTime";
   $adb->pquery($sql,array());   
}
print json_encode($responseData);
unset($responseData);
exit;

/***************************************************/
/***************************************************/
/* RM#2680 ST Iframe Lines */

?>