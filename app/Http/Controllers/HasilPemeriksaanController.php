<?php

namespace App\Http\Controllers;

use App\Models\CheckUpResult;
use App\Models\DetailItemPatient;
use App\Models\DetailServicePatient;
use App\Models\HistoryItemMovement;
use App\Models\InPatient;
use App\Models\ListofItems;
use App\Models\ListofServices;
use App\Models\Registration;
use DB;
use Illuminate\Http\Request;
use Validator;

class HasilPemeriksaanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $data = DB::table('check_up_results')
            ->join('users', 'check_up_results.user_id', '=', 'users.id')
            ->join('registrations', 'check_up_results.patient_registration_id', '=', 'registrations.id')
            ->join('patients', 'registrations.patient_id', '=', 'patients.id')
            ->select('check_up_results.id', 'registrations.id_number as registration_number', 'patients.id as patient_id', 'patients.id_member as patient_number', 'patients.pet_category', 'patients.pet_name',
                'registrations.complaint', 'check_up_results.status_finish', 'check_up_results.status_outpatient_inpatient', 'users.fullname as created_by',
                DB::raw("DATE_FORMAT(check_up_results.created_at, '%d %b %Y') as created_at"));

        if ($request->user()->role == 'dokter') {
            $data = $data->where('users.id', '=', $request->user()->id);
        }

        if ($request->branch_id && $request->user()->role == 'admin') {
            $data = $data->where('users.branch_id', '=', $request->branch_id);
        }

        if ($request->keyword) {
            $data = $data->orwhere('registrations.id_number', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_category', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_name', 'like', '%' . $request->keyword . '%')
                ->orwhere('registrations.complaint', 'like', '%' . $request->keyword . '%')
                ->orwhere('created_by', 'like', '%' . $request->keyword . '%');
        }

        if ($request->orderby) {
            $data = $data->orderBy($request->column, $request->orderby);
        }

        $data = $data->orderBy('check_up_results.id', 'desc');

        $data = $data->get();

        return response()->json($data, 200);
    }

    public function create(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $message_patient = [
            'patient_registration_id.unique' => 'Registrasi Pasien ini sudah pernah di input sebelumnya',
        ];

        $validate = Validator::make($request->all(), [
            'patient_registration_id' => 'required|numeric|unique:check_up_results,patient_registration_id',
            'anamnesa' => 'required|string|min:10',
            'sign' => 'required|string|min:10',
            'diagnosa' => 'required|string|min:10',
            'status_finish' => 'required|bool',
            'status_outpatient_inpatient' => 'required|bool',
        ], $message_patient);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        if ($request->status_outpatient_inpatient == true) {

            $messages = [
                'inpatient.required' => 'Deskripsi Kondisi Pasien harus diisi',
                'inpatient.min' => 'Deskripsi Kondisi Pasien harus minimal 10 karakter',
            ];

            $validate2 = Validator::make($request->all(), [
                'inpatient' => 'required|string|min:10',
            ], $messages);

            if ($validate2->fails()) {
                $errors = $validate2->errors()->all();
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }
        }

        //validasi jasa
        $services = $request->service;
        $result_item = json_decode($services, true);

        if (count($result_item) == 0) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['Data Jasa Harus dipilih minimal 1!'],
            ], 422);
        }

        foreach ($result_item as $key_service) {

            $check_service = ListofServices::find($key_service);

            if (is_null($check_service)) {
                return response()->json([
                    'message' => 'The data was invalid.',
                    'errors' => ['Data tidak ditemukan!'],
                ], 404);
            }

            $check_price_service = DB::table('price_services')
                ->select('list_of_services_id')
                ->where('id', '=', $key_service['price_service_id'])
                ->first();

            if (is_null($check_price_service)) {
                return response()->json([
                    'message' => 'The data was invalid.',
                    'errors' => ['Data Daftar Harga Jasa tidak ditemukan!'],
                ], 404);
            }

            $check_service_name = DB::table('list_of_services')
                ->select('service_name')
                ->where('id', '=', $check_price_service->list_of_services_id)
                ->first();

            if (is_null($check_service_name)) {
                return response()->json([
                    'message' => 'The data was invalid.',
                    'errors' => ['Data Daftar Jasa tidak ditemukan!'],
                ], 404);
            }

            if ($key_service['quantity'] <= 0) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Jumlah jasa ' . $check_service_name->service_name . ' belum diisi!'],
                ], 422);
            }
        }

        //validasi item rawat jalan
        if ($request->item) {

            $temp_item = $request->item;

            // $result_item = json_decode(json_encode($temp_item), true);
            $result_item = json_decode($temp_item, true);

            foreach ($result_item as $value_item) {

                $check_price_item = DB::table('price_items')
                    ->select('list_of_items_id')
                    ->where('id', '=', $value_item['price_item_id'])
                    ->first();

                $check_storage = DB::table('list_of_items')
                    ->select('total_item')
                    ->where('id', '=', $check_price_item->list_of_items_id)
                    ->first();

                if (is_null($check_storage)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Jumlah Barang tidak ditemukan!'],
                    ], 404);
                }

                $check_storage_name = DB::table('list_of_items')
                    ->select('item_name')
                    ->where('id', '=', $check_price_item->list_of_items_id)
                    ->first();

                if (is_null($check_storage_name)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Jumlah Barang tidak ditemukan!'],
                    ], 404);
                }

                if ($value_item['quantity'] <= 0) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Jumlah barang ' . $check_storage_name->item_name . ' belum diisi!'],
                    ], 422);
                }

                if ($value_item['quantity'] > $check_storage->total_item) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Jumlah stok ' . $check_storage_name->item_name . ' pada rawat jalan kurang atau habis!'],
                    ], 422);
                }

                $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                if (is_null($list_of_items)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data tidak ditemukan!'],
                    ], 404);
                }
            }
        }

        //insert data
        $item = CheckUpResult::create([
            'patient_registration_id' => $request->patient_registration_id,
            'anamnesa' => $request->anamnesa,
            'sign' => $request->sign,
            'diagnosa' => $request->diagnosa,
            'status_finish' => $request->status_finish,
            'status_outpatient_inpatient' => $request->status_outpatient_inpatient,
            'status_paid_off' => 0,
            'user_id' => $request->user()->id,
        ]);

        if ($request->status_finish == true) {

            $registration = Registration::find($request->patient_registration_id);
            $registration->user_update_id = $request->user()->id;
            $registration->acceptance_status = 3;
            $registration->updated_at = \Carbon\Carbon::now();
            $registration->save();
        }

        $services = $request->service;
        $result_item = json_decode($services, true);

        foreach ($result_item as $key_service) {

            $service_list = DetailServicePatient::create([
                'check_up_result_id' => $item->id,
                'price_service_id' => $key_service['price_service_id'],
                'quantity' => $key_service['quantity'],
                'price_overall' => $key_service['price_overall'],
                'status_paid_off' => 0,
                'user_id' => $request->user()->id,
            ]);
        }

        if (!(is_null($request->item))) {

            $result_item = json_decode($request->item, true);

            foreach ($result_item as $value_item) {

                $item_list = DetailItemPatient::create([
                    'check_up_result_id' => $item->id,
                    'price_item_id' => $value_item['price_item_id'],
                    'quantity' => $value_item['quantity'],
                    'price_overall' => $value_item['price_overall'],
                    'status_paid_off' => 0,
                    'user_id' => $request->user()->id,
                ]);

                $check_price_item = DB::table('price_items')
                    ->select('list_of_items_id')
                    ->where('id', '=', $value_item['price_item_id'])
                    ->first();

                $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                $count_item = $list_of_items->total_item - $value_item['quantity'];

                $list_of_items->total_item = $count_item;
                $list_of_items->user_update_id = $request->user()->id;
                $list_of_items->updated_at = \Carbon\Carbon::now();
                $list_of_items->save();

                $item_history = HistoryItemMovement::create([
                    'price_item_id' => $value_item['price_item_id'],
                    'quantity' => $value_item['quantity'],
                    'status' => 'kurang',
                    'user_id' => $request->user()->id,
                ]);
            }
        }

        if ($request->status_outpatient_inpatient == true) {

            $item_list = InPatient::create([
                'check_up_result_id' => $item->id,
                'description' => $request->inpatient,
                'user_id' => $request->user()->id,
            ]);
        }

        return response()->json(
            [
                'message' => 'Tambah Data Berhasil!',
            ], 200
        );
    }

    public function detail(Request $request)
    {
        // if ($request->user()->role == 'resepsionis') {
        //     return response()->json([
        //         'message' => 'The user role was invalid.',
        //         'errors' => ['Akses User tidak diizinkan!'],
        //     ], 403);
        // }

        $data = CheckUpResult::find($request->id);
        //, 'registration', 'user' 'service', 'service_inpatient', 'item', 'item_inpatient'

        $registration = DB::table('registrations')
            ->join('patients', 'registrations.patient_id', '=', 'patients.id')
            ->select('registrations.id_number as registration_number', 'patients.id as patient_id', 'patients.id_member as patient_number', 'patients.pet_category',
                'patients.pet_name', 'patients.pet_gender', 'patients.pet_year_age', 'patients.pet_month_age', 'patients.owner_name', 'patients.owner_address',
                'patients.owner_phone_number', 'registrations.complaint', 'registrations.registrant')
            ->where('registrations.id', '=', $data->patient_registration_id)
            ->first();

        $data->registration = $registration;

        $user = DB::table('check_up_results')
            ->join('users', 'check_up_results.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.username as username')
            ->where('users.id', '=', $data->user_id)
            ->first();

        $data->user = $user;

        $services = DB::table('detail_service_patients')
            ->join('price_services', 'detail_service_patients.price_service_id', '=', 'price_services.id')
            ->join('list_of_services', 'price_services.list_of_services_id', '=', 'list_of_services.id')
            ->join('service_categories', 'list_of_services.service_category_id', '=', 'service_categories.id')
            ->join('users', 'detail_service_patients.user_id', '=', 'users.id')
            ->select('detail_service_patients.id as detail_service_patient_id', 'price_services.id as price_service_id',
                'list_of_services.id as list_of_service_id', 'list_of_services.service_name',
                'detail_service_patients.quantity', DB::raw("TRIM(detail_service_patients.price_overall)+0 as price_overall"),
                'service_categories.category_name', DB::raw("TRIM(price_services.selling_price)+0 as selling_price"),
                'users.fullname as created_by', DB::raw("DATE_FORMAT(detail_service_patients.created_at, '%d %b %Y') as created_at"))
            ->where('detail_service_patients.check_up_result_id', '=', $data->id)
            ->orderBy('detail_service_patients.id', 'desc')
            ->get();

        $data['services'] = $services;

        $item = DB::table('detail_item_patients')
            ->join('price_items', 'detail_item_patients.price_item_id', '=', 'price_items.id')
            ->join('list_of_items', 'price_items.list_of_items_id', '=', 'list_of_items.id')
            ->join('category_item', 'list_of_items.category_item_id', '=', 'category_item.id')
            ->join('unit_item', 'list_of_items.unit_item_id', '=', 'unit_item.id')
            ->join('users', 'detail_item_patients.user_id', '=', 'users.id')
            ->select('detail_item_patients.id as detail_item_patients_id', 'list_of_items.id as list_of_item_id', 'price_items.id as price_item_id', 'list_of_items.item_name', 'detail_item_patients.quantity',
                DB::raw("TRIM(detail_item_patients.price_overall)+0 as price_overall"), 'unit_item.unit_name',
                'category_item.category_name', DB::raw("TRIM(price_items.selling_price)+0 as selling_price"),
                'users.fullname as created_by', DB::raw("DATE_FORMAT(detail_item_patients.created_at, '%d %b %Y') as created_at"))
            ->where('detail_item_patients.check_up_result_id', '=', $data->id)
            ->orderBy('detail_item_patients.id', 'desc')
            ->get();

        $data['item'] = $item;

        $inpatient = DB::table('in_patients')
            ->join('users', 'in_patients.user_id', '=', 'users.id')
            ->select('in_patients.description', DB::raw("DATE_FORMAT(in_patients.created_at, '%d %b %Y') as created_at"),
                'users.fullname as created_by')
            ->where('in_patients.check_up_result_id', '=', $data->id)
            ->get();

        $data['inpatient'] = $inpatient;

        return response()->json($data, 200);
    }

    public function update(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        //validasi data hasil pemeriksaaan
        $validate = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'patient_registration_id' => 'required|numeric',
            'anamnesa' => 'required|string|min:10',
            'sign' => 'required|string|min:10',
            'diagnosa' => 'required|string|min:10',
            'status_outpatient_inpatient' => 'required|bool',
            'status_finish' => 'required|bool',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $check_up_result = CheckUpResult::find($request->id);

        if (is_null($check_up_result)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Hasil Pemeriksaan tidak ada!'],
            ], 404);
        }

        if ($request->status_outpatient_inpatient == true) {

            $messages = [
                'inpatient.required' => 'Deskripsi Kondisi Pasien harus diisi',
                'inpatient.min' => 'Deskripsi Kondisi Pasien harus minimal 10 karakter',
            ];

            $validate2 = Validator::make($request->all(), [
                'inpatient' => 'required|string|min:10',
            ], $messages);

            if ($validate2->fails()) {
                $errors = $validate2->errors()->all();

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }
        }

        //validasi data jasa

        $temp_services = $request->service;

        $services = json_decode(json_encode($temp_services), true);

        if (count($services) == 0) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['Data Jasa Harus dipilih minimal 1!'],
            ], 422);
        }

        foreach ($services as $key_service) {

            if (!(is_null($key_service['id']))) {

                //$detail_service_patient = DetailServicePatient::find($key_service['id']);

                //$detail_service_patient
                $check_price_service = DB::table('price_services')
                    ->select('list_of_services_id')
                    ->where('id', '=', $key_service['price_service_id'])
                    ->first();

                if (is_null($check_price_service)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Harga Jasa tidak ditemukan!'],
                    ], 404);
                }

                $check_service = ListofServices::find($check_price_service->list_of_services_id);

                if (is_null($check_service)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Daftar Jasa tidak ditemukan!'],
                    ], 404);
                }

                $check_service_name = DB::table('list_of_services')
                    ->select('service_name')
                    ->where('id', '=', $check_price_service->list_of_services_id)
                    ->first();

                if (is_null($check_service_name)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Daftar Jasa tidak ditemukan!'],
                    ], 404);
                }

                if ($key_service['quantity'] <= 0) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Jumlah jasa ' . $check_service_name->service_name . ' belum diisi!'],
                    ], 422);
                }
            } else {

                $check_price_service = DB::table('price_services')
                    ->select('list_of_services_id')
                    ->where('id', '=', $key_service['price_service_id'])
                    ->first();

                if (is_null($check_price_service)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Harga Jasa Tidak ditemukan!'],
                    ], 404);
                }

                $check_service_name = DB::table('list_of_services')
                    ->select('service_name')
                    ->where('id', '=', $check_price_service->list_of_services_id)
                    ->first();

                if (is_null($check_service_name)) {
                    return response()->json([
                        'message' => 'The data was invalid.',
                        'errors' => ['Data Daftar Jasa tidak ditemukan!'],
                    ], 404);
                }

                if ($key_service['quantity'] <= 0) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Jumlah jasa ' . $check_service_name->service_name . ' belum diisi!'],
                    ], 422);
                }
            }
        }

        //validasi data barang

        if ($request->item) {

            $temp_item = $request->item;

            $result_item = json_decode(json_encode($temp_item), true);

            foreach ($result_item as $value_item) {

                //cek untuk melakukan update atau create

                if (is_null($value_item['id'])) {
                    //$detail_item
                    //kalau data baru

                    $check_price_item = DB::table('price_items')
                        ->select('list_of_items_id')
                        ->where('id', '=', $value_item['price_item_id'])
                        ->first();

                    if (is_null($check_price_item)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data Harga Barang tidak ditemukan!'],
                        ], 404);
                    }

                    $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                    if (is_null($list_of_items)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data Daftar Barang tidak ditemukan!'],
                        ], 404);
                    }

                    $check_storage = DB::table('list_of_items')
                        ->select('total_item')
                        ->where('id', '=', $check_price_item->list_of_items_id)
                        ->first();

                    if (is_null($check_storage)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data jumlah barang tidak ditemukan!'],
                        ], 404);
                    }

                    $check_storage_name = DB::table('list_of_items')
                        ->select('item_name')
                        ->where('id', '=', $check_price_item->list_of_items_id)
                        ->first();

                    if (is_null($check_storage_name)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data jumlah barang tidak ditemukan!'],
                        ], 404);
                    }

                    if ($value_item['quantity'] > $check_storage->total_item) {
                        return response()->json([
                            'message' => 'The given data was invalid.',
                            'errors' => ['Jumlah stok ' . $check_storage_name->item_name . ' kurang atau habis!'],
                        ], 422);
                    }

                    if ($value_item['quantity'] <= 0) {
                        return response()->json([
                            'message' => 'The given data was invalid.',
                            'errors' => ['Jumlah barang ' . $check_storage_name->item_name . ' belum diisi!'],
                        ], 422);
                    }

                } else {

                    $detail_item = DetailItemPatient::find($value_item['id']);
                    //kalau data yang sudah pernah ada

                    //untuk mendapatkan data stok terupdate
                    $check_price_item = DB::table('price_items')
                        ->select('list_of_items_id')
                        ->where('id', '=', $value_item['price_item_id'])
                        ->first();

                    if (is_null($check_price_item)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data Price Item not found!'],
                        ], 404);
                    }

                    $check_stock = DB::table('list_of_items')
                        ->select('total_item')
                        ->where('id', '=', $check_price_item->list_of_items_id)
                        ->first();

                    if (is_null($check_stock)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data Daftar Barang tidak ditemukan!'],
                        ], 404);
                    }

                    $check_storage_name = DB::table('list_of_items')
                        ->select('item_name')
                        ->where('id', '=', $check_price_item->list_of_items_id)
                        ->first();

                    if (is_null($check_storage_name)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data jumlah barang tidak ditemukan!'],
                        ], 404);
                    }

                    //untuk cek quantity yang sudah ada untuk mencari selisih penambahan
                    // $check_item_result = DB::table('detail_item_out_patients')
                    //     ->select('quantity')
                    //     ->where('check_up_result_id', '=', $request->id)
                    //     ->where('item_id', '=', $value_item['item_id'])
                    //     ->first();

                    $check_item_result = DB::table('detail_item_patients')
                        ->join('price_items', 'detail_item_patients.price_item_id', '=', 'price_items.id')
                        ->join('list_of_items', 'price_items.list_of_items_id', '=', 'list_of_items.id')
                        ->select('detail_item_patients.quantity as quantity')
                        ->where('list_of_items.id', '=', $check_price_item->list_of_items_id)
                        ->where('price_items.id', '=', $value_item['price_item_id'])
                        ->first();

                    if (is_null($check_item_result)) {
                        return response()->json([
                            'message' => 'The data was invalid.',
                            'errors' => ['Data Hasil Pemeriksaan tidak ditemukan!'],
                        ], 404);
                    }

                    //validasi kalau data input lebih dari data awal
                    if ($value_item['quantity'] > $check_item_result->quantity) {

                        $res_value_item = $value_item['quantity'] - $check_item_result->quantity;

                        if ($res_value_item > $check_stock->total_item) {
                            return response()->json([
                                'message' => 'The given data was invalid.',
                                'errors' => ['Jumlah stok ' . $check_storage_name->item_name . ' kurang atau habis!'],
                            ], 422);
                        }

                        $check_price_item = DB::table('price_items')
                            ->select('list_of_items_id')
                            ->where('id', '=', $value_item['price_item_id'])
                            ->first();

                        if (is_null($check_price_item)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data Harga Barang tidak ditemukan!'],
                            ], 404);
                        }

                        $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                        if (is_null($list_of_items)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data Daftar Barang tidak ditemukan!'],
                            ], 404);
                        }

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        if (is_null($detail_item_patient)) {

                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data tidak ditemukan!'],
                            ], 404);
                        }

                    } elseif ($value_item['quantity'] < $check_item_result->quantity) {

                        $res_value_item = $check_item_result->quantity - $value_item['quantity'];

                        $check_price_item = DB::table('price_items')
                            ->select('list_of_items_id')
                            ->where('id', '=', $value_item['price_item_id'])
                            ->first();

                        if (is_null($check_price_item)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data Harga Barang tidak ditemukan!'],
                            ], 404);
                        }

                        $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                        if (is_null($list_of_items)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data tidak ditemukan!'],
                            ], 404);
                        }

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        if (is_null($detail_item_patient)) {

                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data tidak ditemukan!'],
                            ], 404);
                        }
                    } else {

                        $check_price_item = DB::table('price_items')
                            ->select('list_of_items_id')
                            ->where('id', '=', $value_item['price_item_id'])
                            ->first();

                        if (is_null($check_price_item)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data Harga Barang tidak ditemukan!'],
                            ], 404);
                        }

                        $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                        if (is_null($list_of_items)) {
                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data tidak ditemukan!'],
                            ], 404);
                        }

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        if (is_null($detail_item_patient)) {

                            return response()->json([
                                'message' => 'The data was invalid.',
                                'errors' => ['Data tidak ditemukan!'],
                            ], 404);
                        }
                    }

                }
            }
        }

        //update hasil pemeriksaan

        $check_up_result = CheckUpResult::find($request->id);

        if (is_null($check_up_result)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Hasil Pemeriksaan tidak ditemukan!'],
            ], 404);
        }

        $check_up_result->patient_registration_id = $request->patient_registration_id;
        $check_up_result->anamnesa = $request->anamnesa;
        $check_up_result->sign = $request->sign;
        $check_up_result->diagnosa = $request->diagnosa;
        $check_up_result->status_outpatient_inpatient = $request->status_outpatient_inpatient;
        $check_up_result->status_finish = $request->status_finish;
        $check_up_result->user_update_id = $request->user()->id;
        $check_up_result->updated_at = \Carbon\Carbon::now();
        $check_up_result->save();

        if ($request->status_finish == true) {

            $registration = Registration::find($request->patient_registration_id);
            $registration->user_update_id = $request->user()->id;
            $registration->acceptance_status = 3;
            $registration->updated_at = \Carbon\Carbon::now();
            $registration->save();
        }

        //update jasa

        foreach ($services as $key_service) {

            if (is_null($key_service['id'])) {

                $service_list = DetailServicePatient::create([
                    'check_up_result_id' => $check_up_result->id,
                    'price_service_id' => $key_service['price_service_id'],
                    'quantity' => $key_service['quantity'],
                    'price_overall' => $key_service['price_overall'],
                    'user_id' => $request->user()->id,
                ]);

            } elseif ($key_service['status'] == 'del' || $value_item['quantity'] == 0) {

                if (!is_null($key_service['id'])) {

                    $detail_service_patient = DetailServicePatient::find($key_service['id']);
                    $detail_service_patient->delete();
                }

            } else {

                $detail_service_patient = DetailServicePatient::find($key_service['id']);

                $detail_service_patient->check_up_result_id = $check_up_result->id;
                $detail_service_patient->price_service_id = $key_service['price_service_id'];
                $detail_service_patient->quantity = $key_service['quantity'];
                $detail_service_patient->price_overall = $key_service['price_overall'];
                $detail_service_patient->user_update_id = $request->user()->id;
                $detail_service_patient->updated_at = \Carbon\Carbon::now();
                $detail_service_patient->save();

            }
        }

        //update barang
        if ($request->item) {

            $temp_item = $request->item;

            $result_item = json_decode(json_encode($temp_item), true);

            foreach ($result_item as $value_item) {

                if (is_null($value_item['id'])) {
                    //$detail_item
                    $detail_item = DetailItemPatient::find($value_item['id']);

                    $item_list = DetailItemPatient::create([
                        'check_up_result_id' => $check_up_result->id,
                        'price_item_id' => $value_item['price_item_id'],
                        'quantity' => $value_item['quantity'],
                        'price_overall' => $value_item['price_overall'],
                        'user_id' => $request->user()->id,
                    ]);

                    $check_price_item = DB::table('price_items')
                        ->select('list_of_items_id')
                        ->where('id', '=', $value_item['price_item_id'])
                        ->first();

                    $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                    $count_item = $list_of_items->total_item - $value_item['quantity'];

                    $list_of_items->total_item = $count_item;
                    $list_of_items->user_update_id = $request->user()->id;
                    $list_of_items->updated_at = \Carbon\Carbon::now();
                    $list_of_items->save();

                    $item_history = HistoryItemMovement::create([
                        'price_item_id' => $value_item['price_item_id'],
                        'quantity' => $value_item['quantity'],
                        'status' => 'kurang',
                        'user_id' => $request->user()->id,
                    ]);

                } elseif ($value_item['status'] == 'del' || $value_item['quantity'] == 0) {

                    $detail_item = DetailItemPatient::find($value_item['id']);
                    // $check_item_result = DB::table('detail_item_patients')
                    //     ->select('quantity')
                    //     ->where('check_up_result_id', '=', $request->id)
                    //     ->where('item_id', '=', $value_item['item_id'])
                    //     ->first();
                    $check_price_item = DB::table('price_items')
                        ->select('list_of_items_id')
                        ->where('id', '=', $value_item['price_item_id'])
                        ->first();

                    $check_item_result = DB::table('detail_item_patients')
                        ->join('price_items', 'detail_item_patients.price_item_id', '=', 'price_items.id')
                        ->join('list_of_items', 'price_items.list_of_items_id', '=', 'list_of_items.id')
                        ->select('detail_item_patients.quantity as quantity')
                        ->where('list_of_items.id', '=', $check_price_item->list_of_items_id)
                        ->where('price_items.id', '=', $value_item['price_item_id'])
                        ->first();

                    $res_value_item = $check_item_result->quantity;

                    $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                    $count_item = $list_of_items->total_item + $res_value_item;

                    $list_of_items->total_item = $count_item;
                    $list_of_items->user_update_id = $request->user()->id;
                    $list_of_items->updated_at = \Carbon\Carbon::now();
                    $list_of_items->save();

                    $item_history = HistoryItemMovement::create([
                        'price_item_id' => $value_item['price_item_id'],
                        'quantity' => $res_value_item,
                        'status' => 'tambah',
                        'user_id' => $request->user()->id,
                    ]);

                    $detail_item->delete();

                } else {

                    //untuk cek quantity yang sudah ada untuk mencari selisih penambahan
                    $check_item_result = DB::table('detail_item_patients')
                        ->select('quantity')
                        ->where('check_up_result_id', '=', $request->id)
                        ->where('price_item_id', '=', $value_item['price_item_id'])
                        ->first();

                    if ($value_item['quantity'] > $check_item_result->quantity) {

                        $res_value_item = $value_item['quantity'] - $check_item_result->quantity;

                        $check_price_item = DB::table('price_items')
                            ->select('list_of_items_id')
                            ->where('id', '=', $value_item['price_item_id'])
                            ->first();

                        $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                        $count_item = $list_of_items->total_item - $res_value_item;

                        $list_of_items->total_item = $count_item;
                        $list_of_items->user_update_id = $request->user()->id;
                        $list_of_items->updated_at = \Carbon\Carbon::now();
                        $list_of_items->save();

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        $detail_item_patient->price_item_id = $value_item['price_item_id'];
                        $detail_item_patient->quantity = $value_item['quantity'];
                        $detail_item_patient->price_overall = $value_item['price_overall'];
                        $detail_item_patient->user_update_id = $request->user()->id;
                        $detail_item_patient->updated_at = \Carbon\Carbon::now();
                        $detail_item_patient->save();

                        $item_history = HistoryItemMovement::create([
                            'price_item_id' => $value_item['price_item_id'],
                            'quantity' => $res_value_item,
                            'status' => 'kurang',
                            'user_id' => $request->user()->id,
                        ]);

                    } elseif ($value_item['quantity'] < $check_item_result->quantity) {

                        $res_value_item = $check_item_result->quantity - $value_item['quantity'];

                        $check_price_item = DB::table('price_items')
                            ->select('list_of_items_id')
                            ->where('id', '=', $value_item['price_item_id'])
                            ->first();

                        $list_of_items = ListofItems::find($check_price_item->list_of_items_id);

                        $count_item = $list_of_items->total_item + $res_value_item;

                        $list_of_items->total_item = $count_item;
                        $list_of_items->user_update_id = $request->user()->id;
                        $list_of_items->updated_at = \Carbon\Carbon::now();
                        $list_of_items->save();

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        $detail_item_patient->price_item_id = $value_item['price_item_id'];
                        $detail_item_patient->quantity = $value_item['quantity'];
                        $detail_item_patient->price_overall = $value_item['price_overall'];
                        $detail_item_patient->user_update_id = $request->user()->id;
                        $detail_item_patient->updated_at = \Carbon\Carbon::now();
                        $detail_item_patient->save();

                        $item_history = HistoryItemMovement::create([
                            'price_item_id' => $value_item['price_item_id'],
                            'quantity' => $res_value_item,
                            'status' => 'tambah',
                            'user_id' => $request->user()->id,
                        ]);

                    } else {

                        $detail_item_patient = DetailItemPatient::find($value_item['id']);

                        $detail_item_patient->price_item_id = $value_item['price_item_id'];
                        $detail_item_patient->quantity = $value_item['quantity'];
                        $detail_item_patient->price_overall = $value_item['price_overall'];
                        $detail_item_patient->user_update_id = $request->user()->id;
                        $detail_item_patient->updated_at = \Carbon\Carbon::now();
                        $detail_item_patient->save();
                    }

                }
            }
        }

        if ($request->status_outpatient_inpatient == true) {

            $item_list = InPatient::create([
                'check_up_result_id' => $request->id,
                'description' => $request->inpatient,
                'user_id' => $request->user()->id,
            ]);
        }

        return response()->json(
            [
                'message' => 'Ubah Data Berhasil!',
            ], 200
        );

    }

    public function delete(Request $request)
    {

        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $check_up_result = CheckUpResult::find($request->id);

        if (is_null($check_up_result)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Hasil Pemeriksaan tidak ditemukan!'],
            ], 404);
        }

        $detail_item = DetailItemPatient::where('check_up_result_id', '=', $request->id)->get();

        if (is_null($detail_item)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Daftar Barang Pasien tidak ditemukan!'],
            ], 404);
        }

        $data_item = [];

        $data_item = $detail_item;

        foreach ($data_item as $datas) {

            $check_price_item = DB::table('price_items')
                ->select('list_of_items_id')
                ->where('id', '=', $datas->price_item_id)
                ->first();

            if (is_null($check_price_item)) {
                return response()->json([
                    'message' => 'The data was invalid.',
                    'errors' => ['Data Harga Barang tidak ditemukan!'],
                ], 404);
            }

            $check_list_of_item = DB::table('list_of_items')
                ->where('id', '=', $check_price_item->list_of_items_id)
                ->first();

            if (is_null($check_list_of_item)) {
                return response()->json([
                    'message' => 'The data was invalid.',
                    'errors' => ['Data Data Daftar Barang Pasien tidak ditemukan!'],
                ], 404);
            }

            $find_prev_stock = DB::table('detail_item_patients')
                ->join('price_items', 'detail_item_patients.price_item_id', '=', 'price_items.id')
                ->join('list_of_items', 'price_items.list_of_items_id', '=', 'list_of_items.id')
                ->select('detail_item_patients.quantity as quantity')
                ->where('list_of_items.id', '=', $check_list_of_item->id)
                ->where('price_items.id', '=', $datas->price_item_id)
                ->first();

            $res_total_item = $check_list_of_item->total_item + $find_prev_stock->quantity;

            $list_of_items = ListofItems::find($check_price_item->list_of_items_id);
            $list_of_items->total_item = $res_total_item;
            $list_of_items->user_update_id = $request->user()->id;
            $list_of_items->updated_at = \Carbon\Carbon::now();
            $list_of_items->save();

            $item_history = HistoryItemMovement::create([
                'price_item_id' => $datas->price_item_id,
                'quantity' => $find_prev_stock->quantity,
                'status' => 'tambah',
                'user_id' => $request->user()->id,
            ]);

            $delete_detail_item_patients = DB::table('detail_item_patients')
                ->where('price_item_id', $datas->price_item_id)->delete();
        }

        $detail_service = DetailServicePatient::where('check_up_result_id', '=', $request->id)->get();

        if (is_null($detail_service)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Jasa Pasien tidak ditemukan!'],
            ], 404);
        }

        $delete_detail_service_patients = DB::table('detail_service_patients')
            ->where('check_up_result_id', $request->id)->delete();

        $inpatient = InPatient::where('check_up_result_id', '=', $request->id)->get();

        if (is_null($inpatient)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data Rawat Inap tidak ditemukan!'],
            ], 404);
        }

        $delete_inpatient = DB::table('in_patients')
            ->where('check_up_result_id', $request->id)->delete();

        $check_up_result = CheckUpResult::find($request->id);
        $check_up_result->delete();

        return response()->json([
            'message' => 'Berhasil menghapus Data',
        ], 200);

    }
}
