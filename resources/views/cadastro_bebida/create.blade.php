@extends('layouts.app')

@section('titulo', 'Enviar receita')
@section('descricao', 'Envie sua receita de drink para entrar no catálogo do Drinkerito.')
@section('robots', 'noindex')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header text-white" style="background: linear-gradient(to right, #2c3e50, #34495e);">
                    <h3 class="text-center font-weight-light my-2">Cadastrar Nova Bebida</h3>
                </div>
                <div class="card-body p-5">
                    


                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('bebida.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nm_bebida" class="form-label">Nome da Bebida</label>
                                <input type="text" name="nm_bebida" id="nm_bebida" class="form-control" required value="{{ old('nm_bebida') }}" placeholder="Ex: Mojito">
                            </div>
                            <div class="col-md-6">
                                <label for="ds_imagem" class="form-label">Imagem da Bebida</label>
                                <input type="file" name="ds_imagem" id="ds_imagem" class="form-control" accept="image/png,image/jpeg,image/jpg">
                                <small class="text-muted">Formatos aceitos: PNG, JPEG (máx. 5MB)</small>
                                <div id="image-preview" class="mt-2" style="display: none;">
                                    <img id="preview-img" src="" alt="Preview" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label d-block">Tipo</label>
                                @foreach(App\Enums\TipoBebida::cases() as $tipo)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="id_tipo"
                                            id="id_tipo_{{ $tipo->value }}" value="{{ $tipo->value }}" required
                                            {{ (int) old('id_tipo', App\Enums\TipoBebida::Alcoolica->value) === $tipo->value ? 'checked' : '' }}>
                                        <label class="form-check-label" for="id_tipo_{{ $tipo->value }}">{{ $tipo->label() }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="ds_bebida" class="form-label">Descrição <span class="text-muted">(opcional)</span></label>
                            <textarea name="ds_bebida" id="ds_bebida" class="form-control" rows="2" maxlength="1000" placeholder="Uma frase sobre a bebida: sabor, origem, quando servir...">{{ old('ds_bebida') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="ds_preparo" class="form-label">Modo de Preparo</label>
                            <textarea name="ds_preparo" id="ds_preparo" class="form-control" rows="4" required placeholder="Descreva o passo a passo...">{{ old('ds_preparo') }}</textarea>
                        </div>

                        <h4 class="mt-4 mb-3 border-bottom pb-2">Ingredientes</h4>
                        <div id="ingredientes-container">
                            <div class="row mb-2 ingrediente-row">
                                <div class="col-md-6">
                                    <select name="ingredientes[0][nm_ingrediente]" class="form-control ingredient-select" required>
                                        <option value="">Selecione um ingrediente</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="text" name="ingredientes[0][ds_medida]" class="form-control" placeholder="Medida (ex: 50ml)">
                                </div>
                                <div class="col-md-1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="add-ingrediente">
                                <i class="fas fa-plus me-1"></i> Adicionar Ingrediente
                            </button>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i> Enviar para Aprovação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- jQuery, select2 e o tema bootstrap-5 do select2. O bloco inline abaixo
     usa $ direto, e por isso o entry precisa vir antes dele. --}}
@vite('resources/js/cadastro-bebida.js')

<script>
    {{-- DOMContentLoaded, e não $(document).ready: este bloco é um script
         clássico e roda durante o parse, enquanto o @vite acima emite um
         módulo, que é deferido. Chamar $ aqui dava "$ is not defined" e
         deixava a tela inteira sem comportamento — select de ingredientes,
         adicionar linha e prévia da imagem. Módulos deferidos executam antes
         do DOMContentLoaded, então aqui dentro o $ já existe. --}}
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: '{{ session('success') }}',
                confirmButtonColor: '#2c3e50'
            });
        @endif

        function initSelect2(element) {
            $(element).select2({
                theme: 'bootstrap-5',
                placeholder: 'Pesquisar ingrediente...',
                allowClear: true,
                tags: true,
                ajax: {
                    url: '{{ route("bebida.ingredientes.search") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                }
            });
        }

        // Init existing select
        initSelect2('.ingredient-select');

        let ingredienteIndex = 1;
        $('#add-ingrediente').click(function() {
            const container = $('#ingredientes-container');
            const row = `
                <div class="row mb-2 ingrediente-row">
                    <div class="col-md-6">
                        <select name="ingredientes[${ingredienteIndex}][nm_ingrediente]" class="form-control ingredient-select" required>
                            <option value="">Selecione um ingrediente</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="ingredientes[${ingredienteIndex}][ds_medida]" class="form-control" placeholder="Medida (ex: 50ml)">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-ingrediente">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            const newRow = $(row);
            container.append(newRow);
            initSelect2(newRow.find('.ingredient-select'));
            ingredienteIndex++;
        });

        $(document).on('click', '.remove-ingrediente', function() {
            $(this).closest('.ingrediente-row').remove();
        });

        $('#ds_imagem').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#preview-img').attr('src', e.target.result);
                    $('#image-preview').show();
                };
                reader.readAsDataURL(file);
            } else {
                $('#image-preview').hide();
            }
        });
    });
</script>
@endsection
