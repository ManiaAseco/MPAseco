<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Donation plugin.
 * Processes planet donations to and payments from the server.
 * Created by Xymph
 *
 * Dependencies: used by plugin.panels.php
 *
 * Important: you must make an initial donation from a player login
 * to your server login via the in-game message system, so that
 * there are sufficient planets in the account to pay the Nadeo tax
 * on the first /donate transaction.
 */

Aseco::registerEvent('onBillUpdated', 'bill_updated');
Aseco::addChatCommand('donate', 'Donates planets to server');
Aseco::addChatCommand('topdons', 'Displays top 100 highest donators');

global $bills, $payments;
$bills = array();
$payments = array();

global $mindonation, $publicappr;
$mindonation = 10;  // minimum donation amount (because of Nadeo tax)
$publicappr = 100;  // public appreciation threshold (show Thank You to all)

global $donation_values;  // default planets values for donate panel
$donation_values = array(20, 50, 100, 200, 500, 1000, 2000);

function chat_donate($aseco, $command) {
	global $bills, $mindonation;

	$player = $command['author'];
	$login = $player->login;
	$planets = $command['params'];

	// check for valid amount
	if ($planets != '' && is_numeric($planets)) {
		$planets = (int) $planets;
		// check for minimum donation
		if ($planets >= $mindonation) {
			// start the transaction
			$message = formatText($aseco->getChatMessage('DONATION'),
			                      $planets, $aseco->server->name);
			$aseco->client->query('SendBill', $login, $planets,
			                      $aseco->formatColors($message), '');
			$billid = $aseco->client->getResponse();
			$bills[$billid] = array($player->login, $player->nickname, $planets);
		} else {
			$message = formatText($aseco->getChatMessage('DONATE_MINIMUM'),
			                      $mindonation);
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		}
	} else {
		$message = $aseco->getChatMessage('DONATE_HELP');
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // chat_donate

function admin_payment($aseco, $login, $target, $amount) {
	global $payments;

	// check parameters
	if ($target != '' && $amount != '' && is_numeric($amount) && $amount > 0) {
		// check for this server
		if ($target != $aseco->server->serverlogin) {
			// get current server planets
			$aseco->client->query('GetServerPlanets');
			$planets = $aseco->client->getResponse();

			// check for sufficient balance, including Nadeo tax (2 + 5%)
			if ($amount <= $planets - 2 - floor($amount * 0.05)) {
				// remember payment to be made
				$label = formatText($aseco->getChatMessage('PAYMENT'), $amount, $target);
				$payments[$login] = array($target, (int) $amount, $label);
				display_payment($aseco, $login, $aseco->server->nickname,
				                $aseco->formatColors($label));
			} else {
				$message = formatText($aseco->getChatMessage('PAY_INSUFF'), $planets);
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		} else {
			$message = $aseco->getChatMessage('PAY_SERVER');
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		}
	} else {
		$message = $aseco->getChatMessage('PAY_HELP');
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // admin_payment

function admin_pay($aseco, $login, $answer) {
	global $bills, $payments;

	// check for confirmation
	if ($answer) {
		// send server planets to login
		$aseco->client->query('Pay', $payments[$login][0], $payments[$login][1],
		                             $aseco->formatColors($payments[$login][2]));
		$billid = $aseco->client->getResponse();
		// store negative bill
		$bills[$billid] = array($login, $payments[$login][0], -$payments[$login][1]);
	} else {
		$message = formatText($aseco->getChatMessage('PAY_CANCEL'),
		                      $payments[$login][0]);
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // admin_pay

// called @ onBillUpdated
// [0]=BillId, [1]=State, [2]=StateName, [3]=TransactionId
function bill_updated($aseco, $bill) {
	global $bills, $publicappr;

	$billid = $bill[0];
	$txid = $bill[3];
	// check for known bill ID
	if (array_key_exists($billid, $bills)) {
		// get bill info
		$login = $bills[$billid][0];
		$nickname = $bills[$billid][1];
		$planets = $bills[$billid][2];

		// check bill state
		switch($bill[1]) {
		case 4:  // Payed (Paid)
			// check for donation or payment
			if ($planets > 0) {
				// check for public appreciation threshold
				if ($planets >= $publicappr) {
					$message = formatText($aseco->getChatMessage('THANKS_ALL'),
					                      $aseco->server->name, $planets, $nickname);
					$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
				} else {
					$message = formatText($aseco->getChatMessage('THANKS_YOU'),
					                      $planets);
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				}
				$aseco->console('Player {1} donated {2} planets to this server (TxId {3})', $login, $planets, $txid);
				ldb_updateDonations($aseco, $login, $planets);

				// throw 'donation' event
				$aseco->releaseEvent('onDonation', array($login, $planets));
			} else {  // $planets < 0
				// get new server planets
				$aseco->client->query('GetServerPlanets');
				$newplanets = $aseco->client->getResponse();

				$message = formatText($aseco->getChatMessage('PAY_CONFIRM'),
				                      abs($planets), $nickname, $newplanets);
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				$aseco->console('Server paid {1} planets to login "{2}" (TxId {3})', abs($planets), $login, $txid);
			}
			unset($bills[$billid]);
			break;
		case 5:  // Refused
			$message = '{#server}> {#error}Transaction refused!';
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			$aseco->console('Refused transaction of {1} to login "{2}" (TxId {3})', $planets, $login, $txid);
			unset($bills[$billid]);
			break;
		case 6:  // Error
			$message = '{#server}> {#error}Transaction failed: {#highlite}$i ' . $bill[2];
			if ($login != '')
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			else
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
			$aseco->console('Failed transaction of {1} to login "{2}" (TxId {3})', $planets, $login, $txid);
			unset($bills[$billid]);
			break;
		default:  // CreatingTransaction/Issued/ValidatingPay(e)ment
			break;
		}
	} else {
		$aseco->console('BillUpdated for unknown BillId {1} {2} (TxId {3})', $billid, $bill[2], $txid);
	}
}  // bill_updated


function chat_topdons($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	$head = 'Current TOP 100 Donators:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, x.Donations FROM players p
	          LEFT JOIN players_extra x ON (p.Id=x.PlayerId)
	          WHERE x.Donations!=0 ORDER BY x.Donations DESC LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) > 0) {
		$dons = array();
		$lines = 0;
		$player->msgs = array();
		// reserve extra width for $w tags
		$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
		$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('Icons128x128_1', 'Coppers', -0.01));
		$i = 1;
		while ($row = mysql_fetch_object($res)) {
			$nick = $row->NickName;
			if (!$aseco->settings['lists_colornicks'])
				$nick = stripColors($nick);
			$dons[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
			                $bgn . $nick, $row->Donations);
			$i++;
			if (++$lines > 14) {
				$player->msgs[] = $dons;
				$lines = 0;
				$dons = array();
			}
		}
		// add if last batch exists
		if (!empty($dons))
			$player->msgs[] = $dons;

		// display ManiaLink message
		display_manialink_multi($player);
	} else {
		$message = '{#server}> {#error}No donator(s) found!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}

	mysql_free_result($res);
}  // chat_topdons
?>
