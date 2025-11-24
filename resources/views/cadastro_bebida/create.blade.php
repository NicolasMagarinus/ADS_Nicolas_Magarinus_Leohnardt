@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white">
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

                    <form method="POST" action="{{ route('bebida.store') }}">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nm_bebida" class="form-label">Nome da Bebida</label>
                                <input type="text" name="nm_bebida" id="nm_bebida" class="form-control" required value="{{ old('nm_bebida') }}" placeholder="Ex: Mojito">
                            </div>
                            <div class="col-md-6">
                                <label for="ds_imagem" class="form-label">URL da Imagem</label>
                                <input type="url" name="ds_imagem" id="ds_imagem" class="form-control" placeholder="https://..." value="{{ old('ds_imagem') }}">
                            </div>
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
                                    <!-- Placeholder for remove button -->
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
                            <a href="{{ route('home') }}" class="btn btn-light">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include jQuery and Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: '{{ session('success') }}',
                confirmButtonColor: '#007bff'
            });
        @endif

        function initSelect2(element) {
            $(element).select2({
                theme: 'bootstrap-5',
                placeholder: 'Pesquisar ingrediente...',
                allowClear: true,
                tags: true, // Allow creating new ingredients
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
    });
</script>
@endsection
