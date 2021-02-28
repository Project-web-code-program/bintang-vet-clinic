$(document).ready(function() {

	const cabangApp = new Vue({
		el: '#cabang-app',
		data: {
			searchTxt: '',
			idCabang: null,
			kodeCabang: '',
			namaCabang: '',
			titleModal: '',
			stateModal: '',
			msgContent: '',
			confirmContent: '',
			kdCabangErr1: false,
			kdCabangErr2: false,
			namaCabangErr: false,
			beErr: false,
			msgBeErr: '',
			listCabang: [],
			columnStatus: {
				branch_code: 'none',
				branch_name: 'none',
			},
			paramUrlSetup: {
				orderby:'',
				column: '',
				keyword: ''
			}
		},
		mounted() {
			if (role.toLowerCase() !== 'admin') {
				window.location.href = $('.baseUrl').val() + `/unauthorized`;	
			}
			this.getData();
		},
		computed: {
			validateSimpanCabang: function() {
				return this.kdCabangErr1 || this.kdCabangErr2 || this.beErr || this.namaCabangErr || !this.touchedForm;
			}
		},
		methods: {
			openFormAdd: function() {
				this.stateModal = 'add';
				this.titleModal = 'Tambah Cabang';
				this.refreshVariable();
				$('#modal-cabang').modal('show');
			},
			openFormUpdate: function(item) {
				this.stateModal = 'edit';
				this.titleModal = 'Ubah Cabang';
				this.refreshVariable();

				this.idCabang   = item.id;
				this.kodeCabang = item.BranchCode;
				this.namaCabang = item.BranchName;
				$('#modal-cabang').modal('show');
			},
			openFormDelete: function(item) {
				this.stateModal = 'delete';
				this.idCabang = item.id;
				this.confirmContent = 'Menghapus cabang akan mempengaruhi keseluruhan data';
				$('#modal-confirmation').modal('show');
			},
			kodeCabangKeyup: function(e) {
				const regexp = /^[^a-z ]*$/;
				this.kdCabangErr2 = (!regexp.test(this.kodeCabang)) ? true : false;

				this.validationForm();
			},
			namaCabangKeyup: function(e) {
				this.validationForm();
			},
			onOrdering: function(e) {

				this.columnStatus[e] = (this.columnStatus[e] == 'asc') ? 'desc' : 'asc';
				if (e === 'branch_code') {
					this.columnStatus['branch_name'] = 'none';
				} else {
					this.columnStatus['branch_code'] = 'none';
				}

				this.paramUrlSetup.orderby = this.columnStatus[e];
				this.paramUrlSetup.column = e;
				this.getData();
			},
			getData: function() {
				$('#loading-screen').show();
				axios.get($('.baseUrl').val() + '/api/cabang', {  params: this.paramUrlSetup, headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }})
					.then(resp => {
						this.listCabang = resp.data;
					})
					.catch(err => {
						if (err.response.status === 401) {
							localStorage.removeItem('vet-clinic');
							location.href = $('.baseUrl').val() + '/masuk';
						}
					})
					.finally(() => {
						$('#loading-screen').hide();
					});
			},
			submitCabang: function() {
				
				if (this.stateModal === 'add') {
					const form_data = new FormData();
					form_data.append('KodeCabang', this.kodeCabang);
					form_data.append('NamaCabang', this.namaCabang);

					this.processSave(form_data);
				} else {

					$('#modal-confirmation').modal('show');
					this.confirmContent = 'Perubahan cabang akan mempengaruhi keseluruhan data';
				}
			},
			submitConfirm: function() {
				const form_data = new FormData();
				form_data.append('id', this.idCabang);

				if (this.stateModal === 'edit') {
					form_data.append('KodeCabang', this.kodeCabang);
					form_data.append('NamaCabang', this.namaCabang);
					this.processEdit(form_data);
				} else {
					this.processDelete({ id: this.idCabang });
				}
			},
			processDelete: function(form_data) {
				axios.delete($('.baseUrl').val() + '/api/cabang', { params: form_data, headers: { 'Authorization': `Bearer ${token}` } })
				.then(resp => {
					if (resp.status == 200) {
						$('#modal-confirmation').modal('toggle');

						this.msgContent = 'Berhasil menghapus cabang';
						$('#msg-box').modal('show');
						this.getData();
					}
				})
				.catch(err => {
					console.log(err);
					if (err.response.status === 401) {
						localStorage.removeItem('vet-clinic');
	          location.href = $('.baseUrl').val() + '/masuk';
					}
				})
			},
			processEdit: function(form_data) {
				axios.post('/cabang/update', form_data)
				.then(resp => {
					if (resp.status == 200) {
						$('#modal-confirmation').modal('toggle');

						this.msgContent = 'Berhasil Mengubah Cabang';
						$('#msg-box').modal('show');

						setTimeout(() => {
							$('#modal-cabang').modal('toggle');
							this.refreshVariable();
							this.getData();
						}, 1000);
					}
				})
				.catch(err => {
					if (err.response.status === 401) {
						localStorage.removeItem('vet-clinic');
	          location.href = $('.baseUrl').val() + '/masuk';
					}
				})
			},
			onSearch: function() {
				this.paramUrlSetup.keyword =  this.searchTxt;
				this.getData();
			},
			processSave: function(form_data) {
				$('#loading-screen').show();
				axios.post($('.baseUrl').val() + '/api/cabang', form_data, { headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` }})
				.then(resp => {
					if(resp.status == 200) {
						this.msgContent = 'Berhasil Menambah Cabang';
						$('#msg-box').modal('show');

						setTimeout(() => {
							$('#modal-cabang').modal('toggle');
							this.refreshVariable();
							this.getData();
						}, 1000);
					}
				})
				.catch(err => {
					if (err.response.status === 422) {
						this.msgBeErr = '',
						err.response.data.errors.forEach((element, idx) => {
							this.msgBeErr += element + ((idx !== err.response.data.errors.length - 1) ? '<br/>' : '');
						});
						this.beErr = true;

					} else if (err.response.status === 401) {
						localStorage.removeItem('vet-clinic');
	          location.href = $('.baseUrl').val() + '/masuk';
					}
				})
				.finally(() => {
					$('#loading-screen').hide();
				});
			},
			validationForm: function() {
				this.touchedForm = true;
				this.kdCabangErr1 = (!this.kodeCabang) ? true : false;
				this.namaCabangErr = (!this.namaCabang) ? true : false;
				this.beErr = false;
			},
			refreshVariable: function() {
				this.kodeCabang = ''; this.namaCabang = '';
				this.kdCabangErr1 = false; this.kdCabangErr2 = false;
				this.namaCabangErr = false; this.touchedForm = false;
				this.beErr = false;
			}
		}
	});

});