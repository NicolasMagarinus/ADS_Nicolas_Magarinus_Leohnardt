<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">Drinkerito</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <form class="d-flex mx-auto position-relative" style="width: 50%;" onsubmit=" return false;">
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Configurações</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-heart me-2"></i> Favoritos</a></li>
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
            img.src = b.ds_imagem ? b.ds_imagem : '/images/default_drink.png'; 
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