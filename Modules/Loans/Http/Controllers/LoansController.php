<?php

namespace Modules\Loans\Http\Controllers;

use Modules\Loans\Utils\LoanUtil;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;
use Auth;
use Illuminate\Support\Facades\DB;
use Modules\Loans\Entities\CreditType;
use Modules\Loans\Entities\Loan;
use Modules\Customers\Entities\Customer;
use Modules\Managers\Entities\Manager;
use Modules\Loans\Entities\Warranty;
use Modules\Loans\Utils\Util;
use Modules\Accounting\Entities\Journal;
use Modules\Accounting\Entities\Account;
use Modules\Loans\Entities\LoanSchedule;
use Modules\Loans\Http\Requests\CreateLoanRequest;
use Modules\Loans\Http\Requests\LoanSimulate;
use Modules\Loans\Http\Requests\UpdateLoanRequest;

class LoansController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $this->authorize('view', Loan::class);

        $loans = Loan::join('customers', 'customers.id', 'loans.customer_id')
            ->join('managers', 'managers.id', 'loans.manager_id')
            ->with('loan_transactions')
            ->select(
                'loans.*',
                'customers.name as customer',
                'managers.name as manager',
            )
            ->get();

        return response()->json($loans);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function simulate(LoanSimulate $request)
    {
        $this->authorize('view', Loan::class);
        $simulation = [];

        $credit_type = CreditType::find($request->credit_type);
        $anual_tax = $credit_type->tax;
        $anual_tax_decimal = $anual_tax / 100;
        $next_due = 1;
        $amount = floatval($request->amount);
        $final_balance = $amount;

        $i = 0;
        while ($final_balance > 0) {
            $fees = floatval($final_balance) * $anual_tax_decimal;
            $tmp['final_balance'] = floatval($final_balance);
            $tmp['effective_payment'] = floatval($amount) / floatval($request->maturity);
            $tmp['index'] = $i + 1 . 'ª Prestação';
            $tmp['scheduled_date'] = LoanUtil::getScheduledDate($credit_type->type, $next_due);
            $final_balance = $final_balance - $tmp['effective_payment'];
            $tmp['fees'] = $fees;
            $tmp['main_capital'] = $tmp['effective_payment'] + $fees;
            array_push($simulation, $tmp);
            $next_due += intval($credit_type->value);
            $i++;
            $next_due++;
        }
        $header_info = [
            'amount' => $request->amount,
            'scheduled_payment' => $amount / floatval($request->maturity),
            'tax' => $credit_type->tax,
            'maturity' => $i++,
            'created_at' => $request->created_at
        ];

        return response()->json(['simulation' => $simulation, 'header_info' => $header_info], 200);

        // if ($first_round) {
        //     $tmp['scheduled_date'] = LoanUtil::getScheduledDate($credit_type->type, $next_due, true);
        //     $tmp['effective_payment'] = 0;
        //     $tmp['final_balance'] = $amount;
        //     $tmp['capital'] = 0;
        //     $tmp['fees'] = 0;
        //     $final_balance = $amount;
        // } else {
        //     floatval($amount) * ((floatval($anual_tax_decimal) / $formater) / (1 - pow(1 + ($anual_tax_decimal / $formater), -floatval($request->maturity))));
        //$first_payment = $i == 1 ? $scheduled_payment : $first_payment;
        // $fees = floatval($final_balance) * (floatval($anual_tax_decimal) / $formater);
        //$fees = floatval($final_balance) * (floatval($anual_tax_decimal) / $formater);
        //$main_capital = floatval($scheduled_payment) - floatval($fees);
        //$tmp['capital'] = number_format($main_capital, 2);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateLoanRequest $request)
    {
        $input = $request->only([
            'amount',
            'credit_type',
            'maturity',
            'created_at',
            'monthly_installment',
            'manager_id',
            'customer_id'
        ]);

        $simulation = [];

        $credit_type = CreditType::find($request->credit_type);
        $anual_tax = $credit_type->tax;
        $anual_tax_decimal = $anual_tax / 100;
        $next_due = 1;
        $amount = floatval($request->amount);
        $final_balance = $amount;

        $i = 0;
        $total_fees = 0;
        while ($final_balance > 0) {
            $fees = floatval($final_balance) * $anual_tax_decimal;
            $tmp['final_balance'] = floatval($final_balance);
            $tmp['effective_payment'] = floatval($amount) / floatval($request->maturity);
            $tmp['index'] = $i + 1;
            $tmp['scheduled_date'] = LoanUtil::getScheduledDate($credit_type->type, $next_due);
            $final_balance = $final_balance - $tmp['effective_payment'];
            $tmp['fees'] = $fees;
            $tmp['main_capital'] = $tmp['effective_payment'] + $fees;
            array_push($simulation, $tmp);
            $next_due += intval($credit_type->value);
            $i++;
            $next_due++;
            $total_fees += $fees;
        }

        $input['created_by'] = auth('api')->user()->id;
        $input['total_fees'] = $total_fees;

        DB::beginTransaction();
        try {
            $loan = Loan::create($input);

            foreach ($request->warranties as $warranty) {
                $warranty = (object) $warranty;
                Warranty::create([
                    'created_by' => auth('api')->user()->id,
                    'description' => $warranty->description,
                    'cost' => $warranty->cost,
                    'value' => $warranty->value,
                    'loan_id' => $loan->id,
                    'acquisition_date' => $warranty->acquisition_date,
                ]);
            }

            ################## Adding Schedules ########################

            foreach ($simulation as $row) {
                LoanSchedule::create([
                    'description' => $row['index'] . 'ª Prestação',
                    'loan_id' => $loan->id,
                    'scheduled_date' => Carbon::create($row['scheduled_date']),
                    'created_by' => auth('api')->user()->id,
                    'scheduled_payment' => $row['effective_payment'],
                    'residual' => $row['final_balance'],
                    'capital_fee' => $row['fees'],
                    'total_monthly' => $row['fees'] + $row['effective_payment']
                ]);
            }

            ################## END Schedules #######################

            DB::commit();

            return response()->json(['success' => 'Criado com sucesso!']);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            DB::rollback();
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
        $this->authorize('view', Loan::class);

        $loan = Loan::where('loans.id', $id)
            ->join('credit_types', 'credit_types.id', 'loans.credit_type')
            ->select(
                'loans.*',
                'credit_types.name as credit_type_name',
                'credit_types.tax as tax',
                'credit_types.id as credit_type'
            )
            ->with('loan_transactions')
            ->first();

        return response()->json([
            'loan' => $loan,
            'customer' => Customer::where('id', $loan->customer_id)
                ->select(
                    'id',
                    'doc_nr',
                    'residence',
                    'activity',
                    'doc_type',
                    'phone',
                    'name',
                    'birthdate',
                    'nuit',
                    'city',
                    'address'
                )
                ->first(),
            'manager' => Manager::where('id', $loan->manager_id)
                ->select('id', 'name')
                ->first(),
            'schedule' => LoanSchedule::where('loan_id', $loan->id)
                ->with('loan_transaction')
                // ->select('id', 'name')
                ->get(),
            'warranties' => Warranty::where('loan_id', $id)->get(),
            'warranties_total_value' => Warranty::where('loan_id', $id)->sum('value'),
            'accounts' => DB::table('accounts')
                ->where('is_account', 1)
                ->where(function ($q) {
                    $q->where('master_parent_id', '1.2')
                        ->orWhere('master_parent_id', '1.1');
                })
                ->get()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLoanRequest $request, Loan $loan)
    {
        if ($loan->status != 'requested') {
            return response()->json(['error' => 'Operacao recusada!'], 500);
        }

        $input = $request->only([
            'amount',
            'credit_type',
            'maturity',
            'created_at',
            'monthly_installment',
            'manager_id',
            'customer_id'
        ]);

        $input['created_by'] = auth('api')->user()->id;

        DB::beginTransaction();
        try {
            $warranties = Warranty::where('loan_id', $loan->id)->get();
            foreach ($warranties as $warranty) {
                $warranty->delete();
            }

            $loan->update($input);

            foreach ($request->warranties as $warranty) {
                $warranty = (object) $warranty;
                Warranty::create([
                    'created_by' => auth('api')->user()->id,
                    'description' => $warranty->description,
                    'cost' => $warranty->cost,
                    'value' => $warranty->value,
                    'loan_id' => $loan->id,
                    'acquisition_date' => $warranty->acquisition_date,
                ]);
            }

            DB::commit();

            return response()->json(['success' => 'Actualizado com sucesso!']);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            DB::rollback();
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approveOrDisapprove(Request $request)
    {
        $loan = Loan::find($request->id);
        if ($loan->status != 'requested') {
            return response()->json(['error' => 'Operacao recusada!'], 500);
        }
        try {
            Loan::where('id', $request->id)->update(['status' => $request->status]);

            return response()->json([
                'success' => 'Aprovado com sucesso!',
                'loan' => Loan::find($request->id)
            ]);
        } catch (\Exception $e) {
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
    public function disburse(Request $request)
    {
        $loan = Loan::find($request->id);
        $amount = $loan->amount;
        $charges = 5; //encargos

        $amounts = [
            'debit' => floatval($loan->amount) + floatval($loan->total_fees),
            'credit1' => floatval($amount) - ($amount * $charges / 100),
            'credit2' => $loan->total_fees,
            'credit3' => $amount * 5 / 100
        ];
        DB::beginTransaction();

        try {
            LoanUtil::castDisburse('Desembolso ' . $loan->code, $amounts, $request->created_at, $request->id, $request->account_id, 1);
            Loan::where('id', $request->id)->update(['status' => 'disbursed', 'disbursed_at' => $request->created_at, 'disbursed_amount' => $request->disbursed_amount]);

            DB::commit();

            return response()->json([
                'success' => 'Deseembolsado com sucesso!',
                'loan' => Loan::find($request->id)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            return response($e, 500);
        }
    }

    public function restruct(Request $request)
    {
        $loan = Loan::find($request->id);
        $total_fees = Loan::totaLFees((object) [
            'maturity' => $loan->maturity,
            'amount' => $request->disbursed_amount,
            'credit_type' => $loan->credit_type
        ]);

        $payed_fee = Loans_transaction::where('loan_id', $request->id)->sum('fees');
        $missing_fees = floatval($total_fees) - floatval($payed_fee);

        $total_payed_installments = Loans_transaction::where('loan_id', $request->id)->sum('effective_payment');

        $new_disbursed_amount = floatval($loan->disbursed_amount) - floatval($total_payed_installments);

        $new_total_fees = Loan::totaLFees((object) [
            'maturity' => $request->maturity,
            'amount' => $new_disbursed_amount,
            'credit_type' => $loan->credit_type
        ]);

        $accountB = Account::where('uuid', '4.9.1.1.1')->firstOrFail(); # Juros a Receber de Creditos Concedidos
        $accountA = Account::where('uuid', '4.1.1.1')->firstOrFail(); #  Créditos Concedidos
        $accountC = Account::where('uuid', '4.1.1.2')->firstOrFail(); #  créditos Cencedidos (Reestruturado)


        ##################################### First cast ################################################

        $last = DB::table('divers')->whereNull('deleted_at')->select('ref')->latest('id')->first();
        if ($last == null) {
            $year_and_month = explode('-', $request->created_at)[0] . '-' . explode('-', $request->created_at)[1];
            $total_records = DB::table('divers')->whereNull('deleted_at')->where('divers.created_at', 'like', '%' . $year_and_month . '%')->count('*');
            $ref = explode('-', $request->created_at)[0] . '' . explode('-', $request->created_at)[1] . '' . str_pad($total_records + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $ref = $last->ref;
        }
        DB::beginTransaction();
        try {
            $divers_first_cast = Divers::create([
                'created_by' => Auth::id(),
                'name' =>  'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'ref' => $ref + 1,
                'description' =>  'Reestruturação​  do crédito ' . $loan->code,
                'debit_acc_id' => $accountB->id,
                'credit_acc_id' => $accountA->id,
                'created_at' => $request->created_at,
            ]);

            Util::encrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'divers_id' => $divers_first_cast->id,
                'account_id' => $accountB->id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'divers_id' => $divers_first_cast->id,
                'account_id' => $accountA->id,
                'date' => $request->created_at,
            ]);


            ######################################## END ####################################################

            ##################################### Second cast ################################################

            $divers_second_cast = Divers::create([
                'created_by' => Auth::id(),
                'name' =>  'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_disbursed_amount),
                'ref' => $ref + 2,
                'description' =>  'Reestruturação​  do crédito ' . $loan->code,
                'debit_acc_id' => $accountC->id,
                'credit_acc_id' => $accountA->id,
                'created_at' => $request->created_at,
            ]);

            Util::encrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_disbursed_amount),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'divers_id' => $divers_second_cast->id,
                'account_id' => $accountC->id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_disbursed_amount),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'divers_id' => $divers_second_cast->id,
                'account_id' => $accountA->id,
                'date' => $request->created_at,
            ]);


            ######################################## END ####################################################

            ##################################### Third cast ################################################

            $divers_third_cast = Divers::create([
                'created_by' => Auth::id(),
                'name' =>  'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'ref' => $ref + 3,
                'description' =>  'Reestruturação​  do crédito ' . $loan->code,
                'debit_acc_id' => $accountC->id,
                'credit_acc_id' => $accountB->id,
                'created_at' => $request->created_at,
            ]);

            Util::encrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'divers_id' => $divers_third_cast->id,
                'account_id' => $accountC->id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => 'Reestruturação​  do crédito ' . $loan->code,
                'amount' => floatval($new_total_fees),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'divers_id' => $divers_third_cast->id,
                'account_id' => $accountB->id,
                'date' => $request->created_at,
            ]);


            ######################################## END ####################################################

            Loan::find($request->id)->update(['status' => 'finished']);
            $input = [
                'amount' => $new_disbursed_amount,
                'credit_type' => $loan->credit_type,
                'maturity' => $request->maturity,
                'created_at' => $request->created_at,
                'monthly_installment' => $loan->monthly_installment,
                'manager_id' => $loan->manager_id,
                'customer_id' => $loan->customer_id,
                'status' => 'disbursed',
                'disbursed_at' => $request->created_at,
                'disbursed_amount' => $new_disbursed_amount
            ];

            $input['created_by'] = Auth::id();


            $new_loan = Loan::create($input);
            $warranties = Warranty::where('loan_id', $request->id)->select('id')->get();

            foreach ($warranties as $warranty) {
                $warranty = (object) $warranty;
                Warranty::find($warranty->id)->update([
                    'created_by' => Auth::id(),
                    'loan_id' => $new_loan->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => 'Reestruturado com sucesso!',
                'loan' => Loan::find($new_loan->id)
            ]);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            DB::rollback();
            return response($e->getMessage(), 500);
        }
    }
    public function forget_loan(Request $request)
    {
        $loan = Loan::find($request->id);
        $total_fees = Loan::totaLFees((object) [
            'maturity' => $loan->maturity,
            'amount' => $loan->disbursed_amount,
            'credit_type' => $loan->credit_type
        ]);

        $payed_fee = Loans_transaction::where('loan_id', $request->id)->sum('fees');
        $missing_fees = floatval($total_fees) - floatval($payed_fee);

        $total_payed_installments = Loans_transaction::where('loan_id', $request->id)->sum('effective_payment');

        $missing_disbursed_amount = floatval($loan->disbursed_amount) - floatval($total_payed_installments);
        $missing_payment = floatval($missing_fees) + floatval($missing_disbursed_amount);

        $accountB = Account::where('uuid', '4.9.1.1.1')->firstOrFail(); # Juros a Receber de Creditos Concedidos
        $accountA = Account::where('uuid', '4.1.1.1')->firstOrFail(); #  Créditos Concedidos
        $accountC = Account::where('uuid', '6.9.1.1')->firstOrFail(); #   Empréstimos bancários


        ##################################### First cast ################################################

        $last = DB::table('divers')->whereNull('deleted_at')->select('ref')->latest('id')->first();
        if ($last == null) {
            $year_and_month = explode('-', $request->created_at)[0] . '-' . explode('-', $request->created_at)[1];
            $total_records = DB::table('divers')->whereNull('deleted_at')->where('divers.created_at', 'like', '%' . $year_and_month . '%')->count('*');
            $ref = explode('-', $request->created_at)[0] . '' . explode('-', $request->created_at)[1] . '' . str_pad($total_records + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $ref = $last->ref;
        }
        DB::beginTransaction();
        try {
            $divers_first_cast = Divers::create([
                'created_by' => Auth::id(),
                'name' =>  'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_disbursed_amount),
                'ref' => $ref + 1,
                'description' =>  'Abate do crédito ' . $loan->code,
                'debit_acc_id' => $accountC->id,
                'credit_acc_id' => $accountA->id,
                'created_at' => $request->created_at,
            ]);

            Util::encrease((object) [
                'description' => 'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_disbursed_amount),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'divers_id' => $divers_first_cast->id,
                'account_id' => $accountC->id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => 'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_disbursed_amount),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'divers_id' => $divers_first_cast->id,
                'account_id' => $accountA->id,
                'date' => $request->created_at,
            ]);


            ######################################## END ####################################################

            ##################################### Second cast ################################################

            $divers_second_cast = Divers::create([
                'created_by' => Auth::id(),
                'name' =>  'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_payment),
                'ref' => $ref + 2,
                'description' =>  'Abate do crédito ' . $loan->code,
                'debit_acc_id' => $accountB->id,
                'credit_acc_id' => $accountA->id,
                'created_at' => $request->created_at,
            ]);

            Util::encrease((object) [
                'description' => 'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_payment),
                'type' => 'debit',
                'operation' => 'sum',
                'payment_method' => 'other',
                'divers_id' => $divers_second_cast->id,
                'account_id' => $accountB->id,
                'date' => $request->created_at,
            ]);

            Util::decrease((object) [
                'description' => 'Abate do crédito ' . $loan->code,
                'amount' => floatval($missing_payment),
                'type' => 'credit',
                'operation' => 'sub',
                'payment_method' => 'other',
                'divers_id' => $divers_second_cast->id,
                'account_id' => $accountA->id,
                'date' => $request->created_at,
            ]);


            ######################################## END ####################################################
            Loan::where('id', $request->id)->update(['status' => 'canceled']);

            DB::commit();

            return response()->json([
                'success' => 'Abatido com sucesso!',
                'loan' => Loan::find($loan->id)
            ]);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            DB::rollback();
            return response($e->getMessage(), 500);
        }
    }



    public function monthly_recognization(Request $request)
    {

        try {
            foreach ($request->loans as $loan_id) {
                $loan = Loan::find($loan_id);
                $anual_tax = Credit_type::find($loan->credit_type)->tax;
                $anual_tax_decimal = $anual_tax / 100;
                $initial_balance = 0;
                $final_balance = 0;

                $last_payment = Loans_transaction::select('effective_date', 'final_balance')->latest('effective_date')->first();

                if ($last_payment != null) {
                    $initial_balance = $last_payment->final_balance;
                } else {
                    $initial_balance = $loan->disbursed_amount;
                }

                $fees = floatval($initial_balance) * (floatval($anual_tax_decimal) / 12);

                $accountA = Account::where('uuid', '4.9.1.1.1')->firstOrFail(); # Juros a Receber de Creditos Concedidos
                $accountB = Account::where('uuid', '7.8.1.2')->firstOrFail(); #  Créditos Concedidos


                ##################################### First cast ################################################

                $last = DB::table('divers')->whereNull('deleted_at')->select('ref')->latest('id')->first();
                if ($last == null) {
                    $year_and_month = explode('-', $request->created_at)[0] . '-' . explode('-', $request->created_at)[1];
                    $total_records = DB::table('divers')->whereNull('deleted_at')->where('divers.created_at', 'like', '%' . $year_and_month . '%')->count('*');
                    $ref = explode('-', $request->created_at)[0] . '' . explode('-', $request->created_at)[1] . '' . str_pad($total_records + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $ref = $last->ref;
                }
                DB::beginTransaction();

                $divers_first_cast = Divers::create([
                    'created_by' => Auth::id(),
                    'name' =>  'Reconhecimento mensal de juros ' . $loan->code,
                    'amount' => floatval($fees),
                    'ref' => $ref + 1,
                    'description' =>  'Reconhecimento mensal de juros ' . $loan->code,
                    'debit_acc_id' => $accountA->id,
                    'credit_acc_id' => $accountB->id,
                    'created_at' => $request->created_at,
                ]);

                Util::encrease((object) [
                    'description' => 'Reconhecimento mensal de juros ' . $loan->code,
                    'amount' => floatval($fees),
                    'type' => 'debit',
                    'operation' => 'sum',
                    'payment_method' => 'other',
                    'divers_id' => $divers_first_cast->id,
                    'account_id' => $accountA->id,
                    'date' => $request->created_at,
                ]);

                Util::encrease((object) [
                    'description' => 'Reconhecimento mensal de juros ' . $loan->code,
                    'amount' => floatval($fees),
                    'type' => 'credit',
                    'operation' => 'sum',
                    'payment_method' => 'other',
                    'divers_id' => $divers_first_cast->id,
                    'account_id' => $accountB->id,
                    'date' => $request->created_at,
                ]);
            }



            ######################################## END ####################################################
            DB::commit();

            return response()->json([
                'success' => 'Lançamentos feito com sucesso!',
            ]);
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            DB::rollback();
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reports(Request $request)
    {
        return view('loans.report')->with('managers', Manager::all())->with('credit_types', Credit_type::all());
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function dashboard()
    {
        $this->authorize('view', Loan::class);
        $total = Loan::sum('amount');
        $total_disbursed = Loan::where('status', 'disbursed')->sum('amount');
        $total_pending = Loan::where('status', 'requested')->sum('amount');
        $total_customers = Customer::count('*');

        $dashboard = [
            'total' => $total,
            'disbursed' => $total_disbursed,
            'pending' => $total_pending,
            'customers' => $total_customers
        ];

        return response()->json($dashboard);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reports_filter(Request $request)
    {
        $manager = $request->manager != '' ? $request->manager : 0;
        $credit_type = $request->credit_type != '' ? $request->credit_type : 0;
        $loanList = [];
        $loans = Loan::where('manager_id', $manager == 0 ? '>' : '=', $manager)
            ->where('credit_type', $credit_type == 0 ? '>' : '=', $credit_type)
            ->where('status', 'disbursed')
            ->whereBetween('loans.created_at', [$request->from, $request->to])
            ->join('customers', 'customers.id', 'loans.customer_id')
            ->select(
                'loans.code',
                'loans.id',
                'loans.credit_type',
                'loans.disbursed_at',
                'loans.disbursed_amount',
                'loans.maturity',
                'customers.name as customer'
            )
            ->groupBy('loans.id')
            ->get();

        foreach ($loans as $loan) {
            $loan->nr_of_installments = Loans_transaction::where('loan_id', $loan->id)->count('*');
            $loan->payed_amount = Loans_transaction::where('loan_id', $loan->id)->sum('effective_payment');
            $loan->current_balance = floatval($loan->disbursed_amount) - $loan->payed_amount;

            switch ($request->type) {
                case 'credit_wallet':
                    array_push($loanList, $loan);
                    break;
                case 'delayed_credit':
                    $last_transaction = Loans_transaction::where('loan_id', $loan->id)->select('effective_date')->latest('id')->first();
                    $dt1 = $last_transaction != null ? Carbon::create($last_transaction->effective_date) : Carbon::create($loan->disbursed_at);
                    $dt1 = $dt1->addMonth();

                    $dt2 = Carbon::parse(date('Y-m-d'));
                    if ($dt2->greaterThan($dt1)) {
                        array_push($loanList, $loan);
                    }
                    break;
                default:
                    $anual_tax = Credit_type::find($loan->credit_type)->tax;
                    $anual_tax_decimal = $anual_tax / 100;
                    $loan->next_payment = floatval($loan->disbursed_amount) * ((floatval($anual_tax_decimal) / 12) / (1 - pow(1 + ($anual_tax_decimal / 12), -floatval($loan->maturity))));
                    array_push($loanList, $loan);
                    break;
            }
        }

        return response()->json(['loans' => $loanList]);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
