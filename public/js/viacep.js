document.addEventListener('DOMContentLoaded', function() {
    const cepInput = document.getElementById('cep');
    if (!cepInput) return;
    cepInput.addEventListener('blur', async function() {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const d = await r.json();
            if (d.erro) { alert('CEP não encontrado.'); return; }
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
            set('endereco', d.logradouro);
            set('bairro', d.bairro);
            set('cidade', d.localidade);
            set('uf', d.uf);
            const numInput = document.getElementById('numero');
            if (numInput) numInput.focus();
        } catch (e) {
            console.error('ViaCEP erro:', e);
        }
    });
});
