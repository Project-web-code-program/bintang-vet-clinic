<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use DB;
use Illuminate\Http\Request;
use Validator;

class PasienController extends Controller
{
    public function index(Request $request)
    {

        $patient = DB::table('patients')
            ->join('branches', 'patients.branch_id', '=', 'branches.id')
            ->join('users', 'patients.user_id', '=', 'users.id')
            ->select('patients.id', 'patients.branch_id', 'branches.branch_name', 'patients.id_member', 'patients.pet_category', 'patients.pet_name', 'patients.pet_gender'
                , 'patients.pet_year_age', 'patients.pet_month_age', 'patients.owner_name', 'patients.owner_address', 'patients.owner_phone_number'
                , 'branches.branch_name', 'users.fullname as created_by',
                DB::raw("DATE_FORMAT(patients.created_at, '%d %b %Y') as created_at"))
            ->where('patients.isDeleted', '=', 'false');

        if ($request->user()->role == 'dokter' || $request->user()->role == 'resepsionis') {
            $patient = $patient->where('patients.branch_id', '=', $request->user()->branch_id);
        }

        if ($request->branch_id && $request->user()->role == 'admin') {
            $patient = $patient->where('patients.branch_id', '=', $request->branch_id);
        }

        if ($request->keyword) {
            $patient = $patient->where('patients.id_member', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_category', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_name', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_gender', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_year_age', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.pet_month_age', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.owner_name', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.owner_address', 'like', '%' . $request->keyword . '%')
                ->orwhere('patients.owner_phone_number', 'like', '%' . $request->keyword . '%')
                ->orwhere('branches.branch_name', 'like', '%' . $request->keyword . '%')
                ->orwhere('users.fullname', 'like', '%' . $request->keyword . '%');
        }

        if ($request->orderby) {

            $patient = $patient->orderBy($request->column, $request->orderby);
        }

        $patient = $patient->orderBy('id', 'desc');

        $patient = $patient->get();

        return response()->json($patient, 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori_hewan' => 'required|min:3|max:50',
            'nama_hewan' => 'required|min:3|max:50',
            'jenis_kelamin_hewan' => 'required|string|max:50',
            'usia_tahun_hewan' => 'required|numeric|min:0',
            'usia_bulan_hewan' => 'required|numeric|min:0|max:12',
            'nama_pemilik' => 'required|string|max:50',
            'alamat_pemilik' => 'required|string|max:100',
            'nomor_ponsel_pengirim' => 'required|numeric|digits_between:10,13',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return response()->json([
                'message' => 'Pasien yang dimasukkan tidak valid!',
                'errors' => $errors,
            ], 422);
        }

        $lastpatient = DB::table('patients')
            ->where('branch_id', '=', $request->user()->branch_id)
            ->count();

        $getbranchuser = DB::table('branches')
            ->select('branch_code')
            ->where('id', '=', $request->user()->branch_id)
            ->first();

        $patient_number = 'BVC-P-' . $getbranchuser->branch_code . '-' . str_pad($lastpatient + 1, 4, 0, STR_PAD_LEFT);

        $patient = Patient::create([
            'id_member' => $patient_number,
            'pet_category' => $request->kategori_hewan,
            'pet_name' => $request->nama_hewan,
            'pet_gender' => $request->jenis_kelamin_hewan,
            'pet_year_age' => $request->usia_tahun_hewan,
            'pet_month_age' => $request->usia_bulan_hewan,
            'owner_name' => $request->nama_pemilik,
            'owner_address' => $request->alamat_pemilik,
            'owner_phone_number' => strval($request->nomor_ponsel_pengirim),
            'branch_id' => $request->user()->branch_id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(
            [
                'message' => 'Tambah Pasien Berhasil!',
            ], 200
        );
    }

    public function update(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'kategori_hewan' => 'required|min:3|max:50',
            'nama_hewan' => 'required|min:3|max:50',
            'jenis_kelamin_hewan' => 'required|string|max:50',
            'usia_tahun_hewan' => 'required|numeric|min:0',
            'usia_bulan_hewan' => 'required|numeric|min:0|max:12',
            'nama_pemilik' => 'required|string|max:50',
            'alamat_pemilik' => 'required|string|max:100',
            'nomor_ponsel_pengirim' => 'required|numeric|digits_between:10,13',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return response()->json([
                'message' => 'Pasien yang dimasukkan tidak valid!',
                'errors' => $errors,
            ], 422);
        }

        $patient = Patient::find($request->id);

        if (is_null($patient)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data tidak ditemukan!'],
            ], 404);
        }

        $patient->pet_category = $request->kategori_hewan;
        $patient->pet_name = $request->nama_hewan;
        $patient->pet_gender = $request->jenis_kelamin_hewan;
        $patient->pet_year_age = $request->usia_tahun_hewan;
        $patient->pet_month_age = $request->usia_bulan_hewan;
        $patient->owner_name = $request->nama_pemilik;
        $patient->owner_address = $request->alamat_pemilik;
        $patient->owner_phone_number = $request->nomor_ponsel_pengirim;
        $patient->user_update_id = $request->user()->id;
        $patient->updated_at = \Carbon\Carbon::now();
        $patient->save();

        return response()->json([
            'message' => 'Berhasil mengupdate Pasien',
        ], 200);
    }

    public function delete(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $patient = Patient::find($request->id);

        if (is_null($patient)) {
            return response()->json([
                'message' => 'The data was invalid.',
                'errors' => ['Data tidak ditemukan!'],
            ], 404);
        }

        $patient->isDeleted = true;
        $patient->deleted_by = $request->user()->fullname;
        $patient->deleted_at = \Carbon\Carbon::now();
        $patient->save();

        $patient->delete();

        return response()->json([
            'message' => 'Berhasil menghapus Pasien',
        ], 200);
    }

    public function patient_accept_only(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $data = DB::table('registrations')
            ->join('users', 'registrations.user_id', '=', 'users.id')
            ->join('users as user_doctor', 'registrations.doctor_user_id', '=', 'user_doctor.id')
            ->join('patients', 'registrations.patient_id', '=', 'patients.id')
            ->join('branches', 'patients.branch_id', '=', 'branches.id')
            ->select('registrations.id as registration_id', 'registrations.id_number as registration_number', 'registrations.patient_id',
                'patients.id_member as id_number_patient', 'patients.pet_category', 'patients.pet_name', 'patients.pet_gender',
                'patients.pet_year_age', 'patients.pet_month_age', 'patients.owner_name', 'patients.owner_address',
                'patients.owner_phone_number', 'complaint', 'registrant', 'user_doctor.id as user_doctor_id',
                'user_doctor.username as username_doctor', 'users.fullname as created_by', 'registrations.acceptance_status',
                DB::raw("DATE_FORMAT(registrations.created_at, '%d %b %Y') as created_at"), 'users.branch_id as user_branch_id',
                'branches.id as branch_id', 'branches.branch_name as branch_name')
            ->where('registrations.acceptance_status', '=', '1');

        if ($request->user()->role == 'dokter') {
            $data = $data
            // ->where('users.branch_id', '=', $request->user()->branch_id)
            ->where('registrations.doctor_user_id', '=', $request->user()->id);
        }

        $data = $data->orderBy('registrations.id', 'desc');

        $data = $data->get();

        return response()->json($data, 200);
    }

    public function HistoryPatient(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $data = DB::table('registrations')
            ->join('users', 'registrations.user_id', '=', 'users.id')
            ->join('users as user_doctor', 'registrations.doctor_user_id', '=', 'user_doctor.id')
            ->join('patients', 'registrations.patient_id', '=', 'patients.id')
            ->join('branches', 'patients.branch_id', '=', 'branches.id')
            ->select('registrations.id as registration_id', 'registrations.id_number as registration_number', 'registrations.patient_id',
                'patients.id_member as id_number_patient', 'patients.pet_category', 'patients.pet_name', 'patients.pet_gender',
                'patients.pet_year_age', 'patients.pet_month_age', 'patients.owner_name', 'patients.owner_address',
                'patients.owner_phone_number', 'complaint', 'registrant', 'user_doctor.id as user_doctor_id',
                'user_doctor.username as username_doctor', 'users.fullname as created_by', 'registrations.acceptance_status',
                DB::raw("DATE_FORMAT(registrations.created_at, '%d %b %Y') as created_at"), 'users.branch_id as user_branch_id',
                'branches.id as branch_id', 'branches.branch_name as branch_name')
            ->where('registrations.acceptance_status', '=', '3')
            ->where('patients.id', '=', $request->patient_id);

        if ($request->user()->role == 'dokter') {
            $data = $data->where('users.branch_id', '=', $request->user()->branch_id)
                ->where('registrations.doctor_user_id', '=', $request->user()->id);
        }

        $data = $data->orderBy('registrations.id', 'desc');

        $data = $data->get();

        return response()->json($data, 200);
    }

    public function DetailHistoryPatient(Request $request)
    {
        if ($request->user()->role == 'resepsionis') {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['Akses User tidak diizinkan!'],
            ], 403);
        }

        $data = DB::table('check_up_results')
            ->join('registrations', 'check_up_results.patient_registration_id', '=', 'registrations.id')
            ->join('users', 'check_up_results.user_id', '=', 'users.id')
            ->select('check_up_results.id as check_up_result_id', 'registrations.id_number as registration_number', 'check_up_results.anamnesa',
                'check_up_results.sign', 'check_up_results.diagnosa', 'check_up_results.status_outpatient_inpatient',
                'check_up_results.status_finish', 'check_up_results.status_paid_off', DB::raw("DATE_FORMAT(check_up_results.created_at, '%d %b %Y') as created_at"),
                'users.fullname as created_by')
            ->where('patient_registration_id', '=', $request->patient_registration_id)
            ->first();

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
            ->where('detail_service_patients.check_up_result_id', '=', $data->check_up_result_id)
            ->orderBy('detail_service_patients.id', 'desc')
            ->get();

        $data->services = $services;

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
            ->where('detail_item_patients.check_up_result_id', '=', $data->check_up_result_id)
            ->orderBy('detail_item_patients.id', 'desc')
            ->get();

        $data->item = $item;

        $inpatient = DB::table('in_patients')
            ->join('users', 'in_patients.user_id', '=', 'users.id')
            ->select('in_patients.id as in_patient_id', 'in_patients.check_up_result_id', 'in_patients.description',
                'users.fullname as created_by', DB::raw("DATE_FORMAT(in_patients.created_at, '%d %b %Y') as created_at"))
            ->where('check_up_result_id', '=', $data->check_up_result_id)
            ->orderBy('in_patients.id', 'desc')
            ->get();

        $data->inpatient = $inpatient;

        return response()->json($data, 200);
    }
}
