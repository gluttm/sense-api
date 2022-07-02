<?php

namespace Modules\Loans\Utils;

use DB;
use Auth;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\AccountTransaction;

class Util
{

    /**
     * decrease product quantity.
     */
    public static function decrease($data = [])
    {
        $account = DB::table('accounts')->where('id', $data->account_id)->first()->balance;

        $last_balance = DB::table('account_transactions')->whereNull('deleted_at')->where('account_id', $data->account_id)->latest('id')->first();
        $last_balance = $last_balance !== null ? $last_balance->final_amount : 0;
        $l_data = [
            'description' => isset($data->description) ? $data->description : null,
            'initial_amount' => isset($account) ? $account : null,
            'amount' => isset($data->amount) ? $data->amount : null,
            'final_amount' => $data->type == 'debit' ? floatval($last_balance) + floatval($data->amount) : floatval($last_balance) - floatval($data->amount),
            'account_id' => isset($data->account_id) ? $data->account_id : null,
            'type' => isset($data->type) ? $data->type : null,
            'operation' => isset($data->operation) ? $data->operation : null,
            'bill_id' => isset($data->bill_id) ? $data->bill_id : null,
            'loan_id' => isset($data->loan_id) ? $data->loan_id : null,
            'journal_id' => isset($data->journal_id) ? $data->journal_id : null,
            'journal_type_id' => isset($data->journal_type_id) ? $data->journal_type_id : null,
            'cost_center_id' => isset($data->cost_center_id) ? $data->cost_center_id : null,
            'invoice_id' => isset($data->invoice_id) ? $data->invoice_id : null,
            'customer_id' => isset($data->customer_id) ? $data->customer_id : null,
            'transaction_id' => isset($data->transaction_id) ? $data->transaction_id : null,
            'created_at' => $data->date !== '' ? $data->date : date('Y-m-d h:i:s'),
            'created_by' => auth('api')->user()->id
        ];

        DB::table('account_transactions')->insert($l_data);
        DB::table('accounts')->where('id', $data->account_id)->decrement('balance', $data->amount);

        if (isset($data->customer_id)) {
            DB::table('customers')->where('id', $data->customer_id)->decrement('balance', $data->amount);
        }
    }

    public static function encrease($data = [])
    {
        $account = DB::table('accounts')->where('id', $data->account_id)->first()->balance;
        $last_balance = DB::table('account_transactions')->whereNull('deleted_at')->where('account_id', $data->account_id)->latest('id')->first();
        $last_balance = $last_balance !== null ? $last_balance->final_amount : 0;
        $e_data = [
            'description' => isset($data->description) ? $data->description : null,
            'initial_amount' => isset($account) ? $account : null,
            'amount' => isset($data->amount) ? $data->amount : null,
            'final_amount' => $data->type == 'debit' ? floatval($last_balance) + floatval($data->amount) : floatval($last_balance) - floatval($data->amount),
            'account_id' => isset($data->account_id) ? $data->account_id : null,
            'type' => isset($data->type) ? $data->type : null,
            'operation' => isset($data->operation) ? $data->operation : null,
            'loan_id' => isset($data->loan_id) ? $data->loan_id : null,
            'bill_id' => isset($data->bill_id) ? $data->bill_id : null,
            'journal_id' => isset($data->journal_id) ? $data->journal_id : null,
            'invoice_id' => isset($data->invoice_id) ? $data->invoice_id : null,
            'journal_type_id' => isset($data->journal_type_id) ? $data->journal_type_id : null,
            'cost_center_id' => isset($data->cost_center_id) ? $data->cost_center_id : null,
            'customer_id' => isset($data->customer_id) ? $data->customer_id : null,
            'transaction_id' => isset($data->transaction_id) ? $data->transaction_id : null,
            'created_at' => $data->date !== '' ? $data->date : date('Y-m-d h:i:s'),
            'created_by' => auth('api')->user()->id
        ];


        DB::table('account_transactions')->insert($e_data);
        // DB::table('extracts')->insert($e_data); expense_cat_id
        DB::table('accounts')->where('id', $data->account_id)->increment('balance', $data->amount);


        if (isset($data->customer_id)) {
            DB::table('customers')->where('id', $data->customer_id)->increment('balance', $data->amount);
        }
    }

    public static function closeAccounts($request, $result_acc, $own_acc)
    {
        // fecho de contas anuais
        $incomes_accounts = Account::where('class_id', '7')->select('balance', 'id')->get();
        $costs_accounts = Account::where('class_id', '6')->select('balance', 'id')->get();
        //dd($incomes_accounts);
        $total_incomes = Account::where('class_id', '7')->select('balance')->sum('balance');
        $total_costs = Account::where('class_id', '6')->select('balance')->sum('balance');
        $period_r = floatval($total_incomes) - floatval($total_costs);

        $accs = Account::select('balance', 'uuid', 'id')->get();
        foreach ($accs as $acc) {
            AccountClosing::create([
                'created_by' => auth('api')->user()->id,
                'uuid' => $acc->uuid,
                'account_id' => $acc->id,
                'balance' => $acc->balance,
                'year' => explode('-', $request->created_at)[0]
            ]);
        }

        //  DB::beginTransaction();
        //try {
        #################################### First Cast ###################################
        // zerar contas de proveito contas de proveito
        $journal = Journal::create([
            'ref' => $request->ref,
            'amount' => $total_incomes,
            'description' => $request->description,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'created_at' => $request->created_at,
            'created_by' => auth('api')->user()->id
        ]);

        foreach ($incomes_accounts as $acc) {
            Util::decrease((object) [
                'description' => $request->description,
                'amount' => $acc->balance,
                'type' => 'debit',
                'operation' => 'sub',
                'journal_id' => $journal->id,
                'cost_center_id' => $request->cost_center,
                'journal_type_id' => $request->journal_type,
                'account_id' => $acc->id,
                'date' => $request->created_at,
                'payment_method' => 'other',
            ]);
        }
        // Credito
        Util::encrease((object) [
            'description' => $request->description,
            'amount' => $total_incomes,
            'type' => 'credit',
            'operation' => 'sum',
            'journal_id' => $journal->id,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'account_id' => $result_acc,
            'date' => $request->created_at,
            'payment_method' => 'other',
        ]);

        ############################# END ######################################


        ######################### Second cast ###################################
        // zerar contas de custos
        $journal2 = Journal::create([
            'ref' => $request->ref + 1,
            'amount' => $total_costs,
            'description' => $request->description,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'created_at' => $request->created_at,
            'created_by' => auth('api')->user()->id
        ]);

        foreach ($costs_accounts as $acc) {
            // caso positivas creditam     (proveitos == passivos , custos == activos)  
            Util::decrease((object) [
                'description' => $request->description,
                'amount' => $acc->balance,
                'type' => 'credit',
                'operation' => 'sub',
                'journal_id' => $journal2->id,
                'cost_center_id' => $request->cost_center,
                'journal_type_id' => $request->journal_type,
                'account_id' => $acc->id,
                'date' => $request->created_at,
                'payment_method' => 'other',
            ]);
        }
        // Credito
        Util::decrease((object) [
            'description' => $request->description,
            'amount' => $total_incomes,
            'type' => 'debit',
            'operation' => 'sum',
            'journal_id' => $journal2->id,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'account_id' => $result_acc,
            'date' => $request->created_at,
            'payment_method' => 'other',
        ]);

        ######################### Third cast ###################################

        $journal3 = Journal::create([
            'ref' => $request->ref + 2,
            'amount' => $period_r,
            'description' => $request->description,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'created_at' => $request->created_at,
            'created_by' => auth('api')->user()->id
        ]);
        // debito
        Util::decrease((object) [
            'description' => $request->description,
            'amount' => $period_r,
            'type' => 'debit',
            'operation' => 'sub',
            'journal_id' => $journal3->id,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'account_id' => $result_acc,
            'date' => $request->created_at,
            'payment_method' => 'other',
        ]);
        // credito
        Util::encrease((object) [
            'description' => $request->description,
            'amount' => $period_r,
            'type' => 'credit',
            'operation' => 'sum',
            'journal_id' => $journal3->id,
            'cost_center_id' => $request->cost_center,
            'journal_type_id' => $request->journal_type,
            'account_id' => $own_acc,
            'date' => $request->created_at,
            'payment_method' => 'other',
        ]);


        // DB::commit();

        //} catch (\Exception $e) {
        //  DB::rollback();
        //\Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

        //  return response($e, 500);
        //}
    }

    public static function set_log($log)
    {
        activity()
            ->causedBy(auth('api')->user()->id)
            ->withProperties(['user' => Auth::user()->username])
            ->log($log);
    }

    public static function rollbackTransactionAccount($identifier, $id)
    {

        $account_transactions = Transactions_account::where($identifier, $id)->get();
        foreach ($account_transactions as $trans) {
            if ($trans->operation == 'sum') {
                Account::find($trans->account_id)->decrement('balance', $trans->amount);
            } else {
                Account::find($trans->account_id)->increment('balance', $trans->amount);
            }
            $trans->delete();
        }
    }
}