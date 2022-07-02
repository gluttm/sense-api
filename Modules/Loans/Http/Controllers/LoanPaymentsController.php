<?php

namespace Modules\Loans\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Loans\Entities\Loan;
use Modules\Loans\Utils\Util;
use Modules\Loans\Entities\LoanTransaction;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\Journal;

use Modules\Loans\Utils\LoanUtil;

class LoanPaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json([
            'accounts' => DB::table('accounts')
                ->where(function ($q) {
                    $q->where('master_parent_id', '1.2')
                        ->orWhere('master_parent_id', '1.1');
                })
                ->get(),
            'loans' => Loan::where('status', 'disbursed')->select('id', 'code')->get(),
            'payments' => LoanTransaction::join('loans', 'loans.id', 'loan_transactions.loan_id')
                ->join('customers', 'loans.customer_id', 'customers.id')
                ->select('loan_transactions.*', 'loans.code as loan', 'customers.name as customer')->get()
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $last_payment = LoanTransaction::select('effective_date', 'final_balance')->latest('effective_date')->first();
        if ($last_payment != null) {
            if ($last_payment->final_balance <= 0) {
                return response()->json([
                    'success' => 'O valor ja foi pago na totalidade.'
                ], 400);
            }
        }

        $getLoan = Loan::find($request->loan);
        if ($getLoan->delay_fees > 0 && $delayed_status) {
            if ($request->effective_payment <= $getLoan->delay_fees) {
                $this->amountWithoutDelayFee = 0;
                $this->newDelayedFee = floatval($getLoan->delay_fees) - floatval($request->effective_payment);
            } else {
                $this->newDelayedFee = 0;
                $this->amountWithoutDelayFee = floatval($request->effective_payment) - floatval($getLoan->delay_fees);
            }
            $getLoan->update(['delay_fees' => $this->newDelayedFee, 'delayed_status' => $newDelayedFee == 0 ? false : true]);
            $this->feePaidAmount = floatval($request->effective_payment) - floatval($this->newDelayedFee);
        }
        $request->amount = $getLoan->delay_fees > 0 && $delayed_status ? $this->amountWithoutDelayFee : $request->effective_payment;
        $input = LoanTransaction::transactionData($request);
        $amount = floatval($input['main_capital']) + floatval($input['fees']);
        # END MATHS

        DB::beginTransaction();

        $description = $request->description ? $request->description : 'Desembolso do Credito ' . $getLoan->code;

        try {
            $journal = LoanUtil::castLoanPayment($description, $request->account_id, $amount, $request->created_at, $getLoan->id, $getLoan->customer->id);

            $input['journal_id'] = $journal->id;

            LoanTransaction::create($input);

            if ($input['final_balance'] <= 0) {
                Loan::find($request->loan)->update(['status' => 'finished']);
            }

            DB::commit();

            return response()->json([
                'success' => 'Rembolsado com sucesso!',
                'loan' => Loan::find($request->id)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return response()->json(LoanTransaction::join('loans', 'loans.id', 'loans_transactions.loan_id')
            ->join('divers', 'divers.id', 'loans_transactions.divers_id')
            ->select('loans_transactions.*', 'loans.code as loan', 'divers.debit_acc_id as account_id')
            ->first($id));
    }

    /**
     * Display the specified resource.
     *
     * 
     * @return \Illuminate\Http\Response
     */
    public function showitall()
    {
        return response()->json(LoanTransaction::join('loans', 'loans.id', 'loans_transactions.loan_id')
            ->select('loans_transactions.*', 'loans.code as loan')->get());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = LoanTransaction::transactionData($request);

        $accountA = Account::where('uuid', '4.1.1.1')->firstOrFail();
        $loans_transaction = LoanTransaction::find($id);
        # END MATHS

        DB::beginTransaction();

        Util::rollbackTransactionAccount('journal_id', $loans_transaction->journal_id);

        try {

            $journal = Journal::find($loans_transaction->journal_id)->update([
                'amount' => floatval($input['main_capital']) + floatval($input['fees']),
                'description' => 'Abate do crédito ' . $loan->code,
                'location_id' => 1,
                'journal_type_id' => 1,
                'created_at' => $request->created_at,
                'created_by' => Auth::id()
            ]);

            Util::increase((object) [
                'description' => $request->description,
                'amount' => floatval($input['main_capital']) + floatval($input['fees']),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'journal_id' => $journal->id,
                'location_id' => 1,
                'journal_type_id' => 1,
                'account_id' => $request->account_id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => $request->description,
                'amount' => floatval($input['main_capital']) + floatval($input['fees']),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'journal_id' => $journal->id,
                'cost_center_id' => 1,
                'journal_type_id' => 1,
                'account_id' => $accountA->id,
                'date' => $request->created_at,
            ]);

            LoanTransaction::find($id)->update($input);

            DB::commit();

            return response()->json([
                'success' => 'Modificado com sucesso!',
                'loan' => Loan::find($request->id)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
