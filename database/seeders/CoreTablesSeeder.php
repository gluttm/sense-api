<?php

use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;

use App\User;

use Spatie\Permission\Models\Role;

use App\Business;

use App\Customer;

use App\Category;

use App\Unit;

use App\Account;

use App\Product;

use App\Supplier;

class CoreTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            ['name'=>'role-list', 'name_to_show'=>'Listar', 'model_name'=>'role'],
            ['name'=>'role-create', 'name_to_show'=>'Criar', 'model_name'=>'role'],
            ['name'=>'role-edit', 'name_to_show'=>'Editar', 'model_name'=>'role'],
            ['name'=>'role-delete', 'name_to_show'=>'Apagar', 'model_name'=>'role'],
 
            // ['name'=>'business-list', 'name_to_show'=>'Listar', 'model_name'=>'business'],
            // ['name'=>'business-create', 'name_to_show'=>'Criar', 'model_name'=>'business'],
            // ['name'=>'business-edit', 'name_to_show'=>'Editar', 'model_name'=>'business'],
            // ['name'=>'business-delete', 'name_to_show'=>'Apagar', 'model_name'=>'business'],
 
            ['name'=>'user-list', 'name_to_show'=>'Listar', 'model_name'=>'user'],
            ['name'=>'user-create', 'name_to_show'=>'Criar', 'model_name'=>'user'],
            ['name'=>'user-edit', 'name_to_show'=>'Editar', 'model_name'=>'user'],
            ['name'=>'user-delete', 'name_to_show'=>'Apagar', 'model_name'=>'user'],
 
            ['name'=>'loan_transaction-list', 'name_to_show'=>'Listar', 'model_name'=>'loan_transaction'],
            ['name'=>'loan_transaction-create', 'name_to_show'=>'Criar', 'model_name'=>'loan_transaction'],
            ['name'=>'loan_transaction-edit', 'name_to_show'=>'Editar', 'model_name'=>'loan_transaction'],
            ['name'=>'loan_transaction-delete', 'name_to_show'=>'Apagar', 'model_name'=>'loan_transaction'],
 
            ['name'=>'warranty-list', 'name_to_show'=>'Listar', 'model_name'=>'warranty'],
            ['name'=>'warranty-create', 'name_to_show'=>'Criar', 'model_name'=>'warranty'],
            ['name'=>'warranty-edit', 'name_to_show'=>'Editar', 'model_name'=>'warranty'],
            ['name'=>'warranty-delete', 'name_to_show'=>'Apagar', 'model_name'=>'warranty'],
 
            ['name'=>'loan-list', 'name_to_show'=>'Listar', 'model_name'=>'loan'],
            ['name'=>'loan-create', 'name_to_show'=>'Criar', 'model_name'=>'loan'],
            ['name'=>'loan-edit', 'name_to_show'=>'Editar', 'model_name'=>'loan'],
            ['name'=>'loan-delete', 'name_to_show'=>'Apagar', 'model_name'=>'loan'],
 
            ['name'=>'business-list', 'name_to_show'=>'Listar', 'model_name'=>'business'],
            ['name'=>'business-create', 'name_to_show'=>'Criar', 'model_name'=>'business'],
            ['name'=>'business-edit', 'name_to_show'=>'Editar', 'model_name'=>'business'],
            ['name'=>'business-delete', 'name_to_show'=>'Apagar', 'model_name'=>'business'],
 
            ['name'=>'customer-list', 'name_to_show'=>'Listar', 'model_name'=>'customer'],
            ['name'=>'customer-create', 'name_to_show'=>'Criar', 'model_name'=>'customer'],
            ['name'=>'customer-edit', 'name_to_show'=>'Editar', 'model_name'=>'customer'],
            ['name'=>'customer-delete', 'name_to_show'=>'Apagar', 'model_name'=>'customer'],

            ['name'=>'manager-list', 'name_to_show'=>'Listar', 'model_name'=>'manager'],
            ['name'=>'manager-create', 'name_to_show'=>'Criar', 'model_name'=>'manager'],
            ['name'=>'manager-edit', 'name_to_show'=>'Editar', 'model_name'=>'manager'],
            ['name'=>'manager-delete', 'name_to_show'=>'Apagar', 'model_name'=>'manager'],
 
            ['name'=>'transaction-list', 'name_to_show'=>'Listar', 'model_name'=>'transaction'],
            ['name'=>'transaction-create', 'name_to_show'=>'Criar', 'model_name'=>'transaction'],
            ['name'=>'transaction-edit', 'name_to_show'=>'Editar', 'model_name'=>'transaction'],
            ['name'=>'transaction-delete', 'name_to_show'=>'Apagar', 'model_name'=>'transaction'],
     
            ['name'=>'account-list', 'name_to_show'=>'Listar', 'model_name'=>'account'],
            ['name'=>'account-create', 'name_to_show'=>'Criar', 'model_name'=>'account'],
            ['name'=>'account-edit', 'name_to_show'=>'Editar', 'model_name'=>'account'],
            ['name'=>'account-delete', 'name_to_show'=>'Apagar', 'model_name'=>'account'],
            
            ['name'=>'credit_type-list', 'name_to_show'=>'Listar', 'model_name'=>'credit_type'],
            ['name'=>'credit_type-create', 'name_to_show'=>'Criar', 'model_name'=>'credit_type'],
            ['name'=>'credit_type-edit', 'name_to_show'=>'Editar', 'model_name'=>'credit_type'],
            ['name'=>'credit_type-delete', 'name_to_show'=>'Apagar', 'model_name'=>'credit_type'],

            
            
         ];

         
         
         foreach ($permissions as $permission) {
            Permission::create($permission);
         }


        
   
        $imageName = '1-logo.png';

        $business = Business::create([
            'business_name' => 'TTMInc',
            'phone' => '8745362672',
            'nuit' => '129988172',
            'owner_id' => '110011882800B',
            'city' => 'Matola',
            'province' => 'Maputo',
            'creation_date' => date('Y-m-d'),
            'image' => $imageName,
        ]);

            $user = User::create([
            'first_name' => 'Naruto',
            'second_name' => 'Uzumaki',
            'username' => 'Uzumaki',
            'gender' => 'M',
            'email' => 'glu@ttm.com',
            'password' => Hash::make('#ttm0000'),
        ]);

        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $permissions = Permission::pluck('id','id')->all();
        $role->syncPermissions($permissions);
        $user->assignRole([$role->id]);
                      
        DB::beginTransaction();
        
        $customer = Customer::create([
            'name' => 'TTMInc',
            'phone' => '8745362672',
            'nuit' => '12998172',
           // 'customer_id' => '110011882800B',
            'city' => 'Matola',
            'country' => 'Moz',
            'province' => 'Maputo',
            'date' => date('Y-m-d'),
            'business_id' => 1,
            'created_by' => 1,
        ]);

        $customer = Supplier::create([
            'name' => 'TTMInc',
            'phone' => '8745362672',
            'nuit' => '12988172',
            'city' => 'Matola',
            'province' => 'Maputo',
            'date' => date('Y-m-d'),
            'country' => 'Moz',
            'business_id' => 1,
            'created_by' => 1,
        ]);

        $category = Category::create([
            'name' => 'FOOD',
            'short_code' => 'TTM0001',
            'created_by' => 1,
            'business_id' => 1,
        ]);

        $units = Unit::create([
            'name' => 'Quilos',
            'allow_decimal' => 1,
            'short_name' => 'kg',
            'created_by' => 1,
            'business_id' => 1,
        ]);

        $business = Product::create([
            'created_by' => 1,
            'name' => 'Pao de Hamburgue',
            'sku' => 'TTM0001',
            'description' => '',
            'category_id' => 1,
            'unit_id' => 1,
            'barcode_type' =>  'code128',
            'alert_quantity' => 15,
            'affects_store' => 1, //in case true, 1 else 0
            'image' => "1-logo.png" ,
            'business_id' => 1,
        ]);



       

        DB::commit();
    }
}
