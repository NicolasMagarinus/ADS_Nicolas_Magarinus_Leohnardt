<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold ms-3" href="{{ route('home') }}" style="font-size: 1.5rem; letter-spacing: 0.5px;">
                <i class="fas fa-cocktail me-2"></i>Drinkerito
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <form class="d-flex flex-grow-1 mx-lg-auto position-relative" style="max-width: 500px;" onsubmit="return false;">
                    <input class="form-control me-2" type="search" id="searchInput" placeholder="Pesquisar..." autocomplete="off">
                    <div id="searchResults" class="list-group position-absolute w-100" style="top: 100%; z-index: 2000; display: none;"></div>
                </form>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('search') }}"><i class="bi bi-compass me-1"></i> Explorar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('random') }}"><i class="bi bi-dice-5-fill me-1"></i> Aleatória</a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('bebida.create') }}"><i class="fas fa-plus-circle me-1"></i> Cadastrar</a>
                        </li>
                        @if(Auth::user()->id_admin)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.bebidas.index') }}"><i class="bi bi-person-fill-lock me-1"></i> Administração</a>
                            </li>
                        @endif
                    @endauth
                    @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('perfil.index') }}"><i class="bi bi-person me-2"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="{{ route('favoritos.index') }}"><i class="bi bi-heart me-2"></i> Favoritos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i> Sair
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    @else
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> Usuário
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('login') }}"><i class="fas fa-sign-in-alt me-2"></i> Login</a></li>
                            <li><a class="dropdown-item" href="{{ route('register') }}"><i class="fas fa-user-plus me-2"></i> Cadastro</a></li>
                        </ul>
                    </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>
</header>

<script>
    document.getElementById('searchInput').addEventListener('keyup', async function () {
        const query = this.value.trim();
        const resultsBox = document.getElementById('searchResults');

        if (query.length < 2) {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = "";
            return;
        }

        const response = await fetch(`/bebida/buscar-bebidas?nome=${encodeURIComponent(query)}`);
        const bebidas = await response.json();

        resultsBox.innerHTML = "";
        resultsBox.style.display = bebidas.length > 0 ? 'block' : 'none';

        bebidas.forEach(b => {
            const item = document.createElement('a');
            item.classList.add('list-group-item', 'list-group-item-action', 'd-flex', 'align-items-center');

            item.href = `/bebida/${b.cd_bebida}`;

            const img = document.createElement('img');
            img.src = b.ds_imagem ? b.ds_imagem : 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png'; 
            img.classList.add('me-2');
            img.style.width = "40px";
            img.style.height = "40px";
            img.style.objectFit = "cover";
            img.style.borderRadius = "6px";

            const text = document.createElement('span');
            text.textContent = b.nm_bebida;

            item.appendChild(img);
            item.appendChild(text);

            resultsBox.appendChild(item);
        });
    });
</script>