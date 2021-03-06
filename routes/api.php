<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::group(['middleware' => ['api']], function () {

    Route::post('masuk', 'UserController@login');

    Route::group(['middleware' => ['jwt.auth']], function () {

        Route::post('keluar', 'UserController@logout');

        //cabang
        Route::get('cabang', 'CabangController@index');
        Route::post('cabang', 'CabangController@create');
        Route::put('cabang', 'CabangController@update');
        Route::delete('cabang', 'CabangController@delete');

        //user management
        Route::get('user', 'UserController@index');
        Route::post('user', 'UserController@register');
        Route::put('user', 'UserController@update');

        Route::get('user/dokter', 'UserController@doctor');

        //pasien
        Route::get('pasien', 'PasienController@index');
        Route::post('pasien', 'PasienController@create');
        Route::put('pasien', 'PasienController@update');
        Route::delete('pasien', 'PasienController@delete');
        Route::get('pasien/status-terima', 'PasienController@patient_accept_only');

        //kategori barang
        Route::get('kategori-barang', 'KategoriBarangController@index');
        Route::post('kategori-barang', 'KategoriBarangController@create');
        Route::put('kategori-barang', 'KategoriBarangController@update');
        Route::delete('kategori-barang', 'KategoriBarangController@delete');

        //satuan barang
        Route::get('satuan-barang', 'SatuanBarangController@index');
        Route::post('satuan-barang', 'SatuanBarangController@create');
        Route::put('satuan-barang', 'SatuanBarangController@update');
        Route::delete('satuan-barang', 'SatuanBarangController@delete');

        //daftar barang
        Route::get('daftar-barang', 'DaftarBarangController@index');
        Route::post('daftar-barang', 'DaftarBarangController@create');
        Route::put('daftar-barang', 'DaftarBarangController@update');
        Route::delete('daftar-barang', 'DaftarBarangController@delete');

        Route::get('daftar-barang/download-template', 'DaftarBarangController@download_template');
        Route::post('daftar-barang/upload', 'DaftarBarangController@upload_template');

        //kategori jasa
        Route::get('kategori-jasa', 'KategoriJasaController@index');
        Route::post('kategori-jasa', 'KategoriJasaController@create');
        Route::put('kategori-jasa', 'KategoriJasaController@update');
        Route::delete('kategori-jasa', 'KategoriJasaController@delete');

        //daftar jasa
        Route::get('daftar-jasa', 'DaftarJasaController@index');
        Route::post('daftar-jasa', 'DaftarJasaController@create');
        Route::put('daftar-jasa', 'DaftarJasaController@update');
        Route::delete('daftar-jasa', 'DaftarJasaController@delete');

        //pembagian harga jasa
        Route::get('pembagian-harga-jasa', 'HargaJasaController@index');
        Route::post('pembagian-harga-jasa', 'HargaJasaController@create');
        Route::put('pembagian-harga-jasa', 'HargaJasaController@update');
        Route::delete('pembagian-harga-jasa', 'HargaJasaController@delete');
        Route::get('pembagian-harga-jasa/kategori-jasa', 'HargaJasaController@service_category');
        Route::get('pembagian-harga-jasa/nama-jasa', 'HargaJasaController@service_name');

        //pembagian harga barang
        Route::get('pembagian-harga-barang', 'HargaBarangController@index');
        Route::post('pembagian-harga-barang', 'HargaBarangController@create');
        Route::put('pembagian-harga-barang', 'HargaBarangController@update');
        Route::delete('pembagian-harga-barang', 'HargaBarangController@delete');
        Route::get('pembagian-harga-barang/kategori-barang', 'HargaBarangController@item_category');
        Route::get('pembagian-harga-barang/nama-barang', 'HargaBarangController@item_name');

        //registrasi pasien
        Route::get('registrasi-pasien', 'RegistrasiController@index');
        Route::post('registrasi-pasien', 'RegistrasiController@create');
        Route::put('registrasi-pasien', 'RegistrasiController@update');
        Route::delete('registrasi-pasien', 'RegistrasiController@delete');

        //penerimaan pasien
        Route::get('penerimaan-pasien', 'PenerimaanPasienController@index');
        Route::get('penerimaan-pasien/terima', 'PenerimaanPasienController@accept');
        Route::get('penerimaan-pasien/tolak', 'PenerimaanPasienController@decline');

        //hasil pemeriksaan
        Route::post('hasil-pemeriksaan', 'HasilPemeriksaanController@create');        
        Route::get('hasil-pemeriksaan', 'HasilPemeriksaanController@index');
        Route::get('hasil-pemeriksaan/detail', 'HasilPemeriksaanController@detail');
        Route::put('hasil-pemeriksaan', 'HasilPemeriksaanController@update');
        Route::delete('hasil-pemeriksaan', 'HasilPemeriksaanController@delete');

        //pembayaran    DropDownPatient
        Route::get('pembayaran/pasien', 'PembayaranController@DropDownPatient');
        Route::get('pembayaran', 'PembayaranController@index');
        Route::post('pembayaran', 'PembayaranController@create');
        Route::put('pembayaran', 'PembayaranController@update');
        Route::get('pembayaran/detail', 'PembayaranController@detail');

        //riwayat pasien
        Route::get('pasien/riwayat', 'PasienController@HistoryPatient');
        Route::get('pasien/detail-riwayat', 'PasienController@DetailHistoryPatient');
    });
});

// Route::post('register', 'UserController@register');
// Route::post('login', 'UserController@login');
// Route::get('book', 'BookController@book');

// Route::get('bookall', 'BookController@bookAuth')->middleware('jwt.verify');
// Route::get('user', 'UserController@getAuthenticatedUser')->middleware('jwt.verify');
