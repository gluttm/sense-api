<?php

namespace Modules\Loans\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\Journal;

class LoanUtil
{

  public static function getScheduledDate($type, $value, $is_first = false)
  {
    $today = Carbon::now('Africa/Maputo');

    # no caso da taxa anual so se aplicara se a pagamentos mensais, trimestrais ou mais
    # o maximo do input pra dias sera 31, para meses, sera 12, para semanas 4
    # fazer com que se sobrar alguma parcela, caso seja taxa mensal, que seja pago no ultimo dia do mes
    # se for anual que seja pago no ultimo mes do ano
    # Para obter o dividendo faremos, se for mensal, 30 / dias caso seja anual sera 12 / meses
    // $tmp = '';
    // if ($type == 'monthly') {
    //   $tmp = $today->addDays(intval($value))->toFormattedDateString();
    // } else if ($type == 'yearly') {
    //   $tmp = $today->addMonths(intval($value))->toFormattedDateString();
    // }
    return $today->addMonths($value)->toFormattedDateString();
  }

  public static function getFormater($type, $value)
  {
    if ($type == 'monthly') {
      return 30 / intval($value);
    }

    if ($type == 'yearly') {
      return 12 / intval($value);
    }
  }

  public static function castDisburse($description, $amounts, $created_at, $loan_id, $request_account, $location_id)
  {
    $config = [
      ['type' => 'debit', 'operation' => 'sum', 'amount' => $amounts['debit'], 'account_id' => Account::getIdFromUUID('4.1.1.1')],
      ['type' => 'credit', 'operation' => 'sub', 'amount' => $amounts['credit1'], 'account_id' => $request_account],
      ['type' => 'credit', 'operation' => 'sub', 'amount' => $amounts['credit2'], 'account_id' => Account::getIdFromUUID('4.9.1.1.1')],
      ['type' => 'credit', 'operation' => 'sum', 'amount' => $amounts['credit3'], 'account_id' => Account::getIdFromUUID('4.6.6')]
    ];

    // first cast
    $journal = Journal::create([
      'ref' => Journal::invoiceNumber($created_at, 1),
      'amount' => $amounts['debit'],
      'description' => $description,
      'location_id' => $location_id,
      'journal_type_id' => 1,
      'loan_id' => $loan_id,
      'created_at' => $created_at,
      'created_by' => Auth::id()
    ]);
    foreach ($config as $conf) {
      $conf = (object) $conf;
      $data = [
        'description' => $description,
        'amount' => $conf->amount,
        'type' => $conf->type,
        'operation' => $conf->operation,
        'journal_id' => $journal->id,
        'location_id' => $location_id,
        'journal_type_id' => $journal->journal_type_id,
        'account_id' => $conf->account_id,
        'date' => $created_at,
        'payment_method' => 'other',
      ];
      if ($conf->operation == 'sum') {
        Util::encrease((object) $data);
      } else {
        Util::decrease((object) $data);
      }
    }
  }

  public static function castLoanPayment($description, $account_id, $amount, $created_at, $bill_id, $customer_id)
  {
    if (!Account::isValid($account_id)) {
      return false;
    }
    $config = [
      ['type' => 'debit', 'operation' => 'sum', 'amount' => $amount, 'account_id' => $account_id],
      ['type' => 'credit', 'operation' => 'sub', 'amount' => $amount, 'is_customer' => true, 'account_id' => Account::getIdFromUUID('4.1.1.1')]
    ];
    if ($amount <= 0) {
      return;
    }
    // first cast
    $journal = Journal::create([
      'ref' => Journal::invoiceNumber($created_at, 1),
      'amount' => $amount,
      'description' => $description,
      'location_id' => 1,
      'journal_type_id' => 1,
      'bill_id' => $bill_id,
      'created_at' => $created_at,
      'created_by' => Auth::id()
    ]);
    foreach ($config as $conf) {
      $conf = (object) $conf;
      $data = [
        'description' => $description,
        'amount' => $conf->amount,
        'type' => $conf->type,
        'operation' => $conf->operation,
        'journal_id' => $journal->id,
        'location_id' => 1,
        'customer_id' => isset($conf->is_customer) ? $customer_id : null,
        'journal_type_id' => $journal->journal_type_id,
        'account_id' => $conf->account_id,
        'date' => $created_at,
        'payment_method' => 'other',
      ];
      if ($conf->amount > 0) {
        if ($conf->operation == 'sum') {
          Util::encrease((object) $data);
        } else {
          Util::decrease((object) $data);
        }
      }
    }
    return $journal;
  }
}
