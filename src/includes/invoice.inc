<table border=0 width=700>

	<tr><td colspan="4" class="center"><span class="highlight">Your Transaction has been Approved</span></td></tr>
	<tr><td colspan="4" class="center"><span class="highlight">Print this receipt for your records</span></td></tr>
	<tr><td colspan="4" bgcolor="#EEEEEE">&nbsp;</td></tr>
	<tr><td align="center" colspan="4"><h2 class="center">Toronto Ultimate Club</h2></td></tr>
	<tr><td align="center" colspan="4">1185 Eglinton Ave. East</td></tr>
	<tr><td align="center" colspan="4">Suite 703</td></tr>
	<tr><td align="center" colspan="4">Toronto, ON</td></tr>
	<tr><td align="center" colspan="4">M3C 3C6</td></tr>
	<tr><td>&nbsp;</td></tr>
	<tr><td align="center" colspan="4">1 416 426 7175</td></tr>

	<tr><td align="center" colspan="4"><a href="http://www.tuc.org/">www.tuc.org</a></td></tr>
	<tr><td>&nbsp;</td></tr>

	<tr bgcolor="#EEEEEE"><td colspan="4"><b>Transaction Type: <?php
		switch ($trans_name)
		{
			case 'purchase':
			case 'cavv_purchase':
				print 'Purchase';
				break;

			case 'idebit_purchase':
				print 'Debit Purchase';
				break;

			case 'preauth':
			case 'cavv_preauth':
				print 'Pre-authorization';
				break;

			default:
				print $trans_name;
		}
	?></b></td></tr>
	<tr><td>Order ID:</td><td><?php print $long_order_id; ?></td></tr>
	<tr>
		<td>Date / Time:</td><td><?php print "$date_stamp  $time_stamp"; ?></td>
		<td>Approval Code:</td><td><?php print $bank_approval_code; ?></td>
	</tr>
	<tr>
		<td nowrap>Sequence Number:</td><td><?php print $bank_transaction_id; ?></td>
		<td>Response&nbsp;/&nbsp;ISO Code:</td><td nowrap><?php print "$response_code/$iso_code"; ?></td>
	</tr>
	<tr>
		<td>Amount (CAD):</td><td>$<?php print $charge_total; ?></td>
		<td>Card #:</td><td><?php print $f4l4; ?></td>
	</tr>
	<tr><td colspan="4" nowrap>Message: <?php print $message; ?></td></tr>
	<tr><td>&nbsp;</td></tr>

	<?php if ($trans_name == 'idebit_purchase') { ?>
	<tr bgcolor="#EEEEEE"><td colspan="4"><b>INTERAC&reg; Online Information</b></td></tr>
	<tr>
		<td>Issuer Name:</td><td><?php print $issuer; ?></td>
	</tr>
	<tr>
		<td>Issuer Confirmation:</td><td><?php print $issuer_invoice; ?></td>
	</tr>
	<tr>
		<td>Issuer Invoice #:</td><td><?php print $issuer_confirmation; ?></td>
	</tr>
	<tr><td>&nbsp;</td></tr>
	<?php } ?>

</table>

<table border="0" cellspacing="1" cellpadding="3" width="700">
	<tr><td colspan=5 bgcolor="#EEEEEE"><strong>Item Information</strong></td></tr>
	<tr>
		<td bgcolor="#DDDDDD" width=100><strong>ID</strong></td>
		<td bgcolor="#DDDDDD" width=350><strong>Description</strong></td>
		<td bgcolor="#DDDDDD" width=50 align="middle"><strong>Qty</strong></td>
		<td bgcolor="#DDDDDD" width=100 align="right"><strong>Unit Cost</strong></td>
		<td bgcolor="#DDDDDD" width=100 align="right"><strong>Subtotal</strong></td>
	</tr>
	<tr> 
		<td valign="top"><?php print sprintf(variable_get('reg_id_format'), $info->registration_id); ?></td> 
		<td valign="top"><?php print $info->name; ?></td> 
		<td valign="top">1</td> 
		<td valign="top" align="right">$<?php print $info->cost; ?></td> 
		<td valign="top" align="right">$<?php print $info->cost; ?></td> 
	</tr> 

<?php if ($info->gst > 0) { ?>
	<tr> 
		<td></td><td></td><td></td> 
		<td align="right">GST:</td> 
		<td align="right">$<?php print $info->gst; ?></td> 
	</tr>
<?php } else { ?>
	<tr><td>&nbsp;</td></tr>
<?php } ?>

<?php if ($info->pst > 0) { ?>
	<tr> 
		<td></td><td></td><td></td> 
		<td align="right">PST:</td> 
		<td align="right">$<?php print $info->pst; ?></td> 
	</tr>
<?php } else { ?>
	<tr><td>&nbsp;</td></tr>
<?php } ?>

	<tr>
		<td></td><td></td><td></td><td align="right">Total:</td>
		<td align="right">$<?php print $charge_total; ?>&nbsp;(CAD)</td>
	</tr>
</table>

<table width="700" cellspacing=3 cellpadding=3>
	<tr><td bgcolor="#EEEEEE"><strong>Customer Information</strong></td></tr>
	<tr>
		<td><?php print "$info->firstname $info->lastname"; ?></td>
	</tr>
	<tr>
		<td><?php print $info->addr_street; ?></td>
	</tr>
	<tr>
		<td><?php print $info->addr_city; ?></td>
	</tr>
	<tr>
		<td><?php print $info->addr_prov; ?></td>
	</tr>
	<tr>
		<td><?php print $info->addr_postalcode; ?></td>
	</tr>
	<tr>
		<td>Canada</td>
	</tr>
	<tr>
		<td><?php print $info->home_phone; ?></td>
	</tr>
	<tr><td>&nbsp;</td></tr>

	<tr><td bgcolor="#EEEEEE"><strong>Registration Refund Policy</strong></td></tr>
	<tr><td>Refund requests should be directed to gm@tuc.org. Refunds are granted under the following conditions:<ul>
		<li>If a member decides to quit after playing less than 25% of their games, a refund will be granted, less a 10% administration fee</li>
		<li>If a member or team is incorrectly charged or charged more than once for their registration a refund will be granted for the incorrect difference</li>
		<li>Pro-rated refunds can be requested in extraordinary circumstances (e.g. if a player becomes seriously injured after playing more than 25% of their games) and are at the discretion of the GM</li>
		<li>Other situations may warrant a refund, at the discretion of the GM</li>
		</ul>Appeals of the GM's refusal to grant a refund or partial refund should be directed to the TUC board at board@tuc.org.</td></tr>

</table>
