<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
	return view('index');
});

Route::get('/masuk', function () {
	return view('auth.login');
});

Route::get('/user', function () {
	return view('user.index');
});

Route::get('/cabang', function () {
	return view('cabang.index');
});

Route::get('/pasien', function () {
	return view('pasien.index');
});

Route::get('/riwayat-pameriksaan/{id}', function () {
	return view('pasien.riwayat-pasien');
});

Route::get('/kategori-barang', function () {
	return view('gudang.kategori-barang.index');
});

Route::get('/satuan-barang', function () {
	return view('gudang.satuan-barang.index');
});

Route::get('/daftar-barang', function () {
	return view('gudang.daftar-barang.index');
});

Route::get('/pembagian-harga-barang', function () {
	return view('gudang.pembagian-harga.index');
});

Route::get('/kategori-jasa', function () {
	return view('layanan.kategori-jasa.index');
});

Route::get('/daftar-jasa', function () {
	return view('layanan.daftar-jasa.index');
});

Route::get('/pembagian-harga-jasa', function () {
	return view('layanan.pembagian-harga.index');
});

// Route::get('/rawat-inap', function () {
// 	return view('pendaftaran.rawat-inap.index');
// });

Route::get('/pendaftaran', function () {
	return view('pendaftaran.pendaftaran-pasien.index');
});

Route::get('/penerimaan-pasien', function () {
	return view('dokter.penerimaan-pasien.index');
});

// Route::get('/dokter-rawat-inap', function () {
// 	return view('dokter.rawat-inap.index');
// });

Route::get('/hasil-pemeriksaan', function () {
	return view('hasil-pemeriksaan.index');
});

Route::get('/pembayaran', function () {
	return view('pembayaran.index');
});

Route::get('/pembayaran/tambah', function () {
	return view('pembayaran.pembayaran-tambah');
});

Route::get('/pembayaran/edit/{id}', function () {
	return view('pembayaran.pembayaran-edit');
});

Route::get('/pembayaran/detail/{id}', function () {
	return view('pembayaran.pembayaran-detail');
});

Route::get('/unauthorized', function () {
	return view('unauthorized');
});