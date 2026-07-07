// Máscaras
function maskCPF(v) { return v.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14); }
function maskCNPJ(v) { return v.replace(/\D/g,'').replace(/(\d{2})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2').slice(0,18); }
function maskCEP(v) { return v.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2').slice(0,9); }
function maskPhone(v) { v=v.replace(/\D/g,''); return v.length>10?v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3'):v.replace(/(\d{2})(\d{4})(\d{4})/,'($1) $2-$3'); }
function maskMoney(v) {
    v = v.replace(/\D/g,'');
    v = (parseInt(v)/100).toFixed(2);
    return v.replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}

document.addEventListener('DOMContentLoaded', function() {
    // CPF/CNPJ dinâmico
    document.querySelectorAll('[data-mask="cpf-cnpj"]').forEach(function(el) {
        el.addEventListener('input', function() {
            const digits = this.value.replace(/\D/g,'');
            this.value = digits.length <= 11 ? maskCPF(this.value) : maskCNPJ(this.value);
        });
    });
    document.querySelectorAll('[data-mask="cpf"]').forEach(function(el) {
        el.addEventListener('input', function() { this.value = maskCPF(this.value); });
    });
    document.querySelectorAll('[data-mask="cnpj"]').forEach(function(el) {
        el.addEventListener('input', function() { this.value = maskCNPJ(this.value); });
    });
    document.querySelectorAll('[data-mask="cep"]').forEach(function(el) {
        el.addEventListener('input', function() { this.value = maskCEP(this.value); });
    });
    document.querySelectorAll('[data-mask="phone"]').forEach(function(el) {
        el.addEventListener('input', function() { this.value = maskPhone(this.value); });
    });
    document.querySelectorAll('[data-mask="money"]').forEach(function(el) {
        el.addEventListener('input', function() { this.value = maskMoney(this.value); });
    });
    document.querySelectorAll('[data-mask="date"]').forEach(function(el) {
        el.addEventListener('input', function() {
            let v = this.value.replace(/\D/g,'').slice(0,8);
            if (v.length >= 5) v = v.replace(/(\d{2})(\d{2})(\d{4})/, '$1/$2/$3');
            else if (v.length >= 3) v = v.replace(/(\d{2})(\d{1,2})/, '$1/$2');
            this.value = v;
        });
    });

    // Tipo pessoa toggle CPF/CNPJ
    document.querySelectorAll('input[name="tipo_pessoa"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const input = document.querySelector('[data-mask="cpf-cnpj"]');
            if (input) {
                input.value = '';
                input.placeholder = this.value === 'FISICA' ? '000.000.000-00' : '00.000.000/0000-00';
            }
        });
    });
});
