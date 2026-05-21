const STORAGE_KEY_AUTH = "imperio_auth_session";
const STORAGE_KEY_USERS = "imperio_auth_users";

const AUTH_DEFAULT_USER = null;

function normalizarEmail(email) {
    return String(email || "").trim().toLowerCase();
}

function obterRotasApp() {
    const usandoPhp = /\.php($|\?)/i.test(window.location.pathname);

    return {
        admin: usandoPhp ? "admin.php" : "admin.html",
        index: usandoPhp ? "index.php" : "index.html",
        login: usandoPhp ? "login.php" : "login.html",
        cadastro: usandoPhp ? "cadastro.php" : "cadastro.html",
        conta: usandoPhp ? "conta.php" : "conta.html",
    };
}

function obterDestinoPosLogin(papel = "cliente") {
    const rotas = obterRotasApp();
    return papel === "admin" ? rotas.admin : rotas.conta;
}

function obterUsuariosLocais() {
    let usuarios = [];

    try {
        const salvos = JSON.parse(localStorage.getItem(STORAGE_KEY_USERS)) || [];
        usuarios = Array.isArray(salvos) ? salvos : [];
    } catch (erro) {
        console.warn("Nao foi possivel ler os usuarios locais.", erro);
    }

    return usuarios;
}

function salvarUsuariosLocais(usuarios) {
    localStorage.setItem(STORAGE_KEY_USERS, JSON.stringify(usuarios));
}

function buscarUsuarioPorEmail(email) {
    const emailNormalizado = normalizarEmail(email);
    return obterUsuariosLocais().find((usuario) => normalizarEmail(usuario.email) === emailNormalizado) || null;
}

function obterSessaoAutenticada() {
    if (window.APP_AUTH && window.APP_AUTH.autenticado) {
        return {
            nome: window.APP_AUTH.nome || "Cliente",
            email: window.APP_AUTH.email || "",
            papel: window.APP_AUTH.papel || "cliente",
        };
    }

    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY_AUTH)) || null;
    } catch (erro) {
        console.warn("Não foi possível ler a sessão atual.", erro);
        return null;
    }
}

function usuarioEstaAutenticado() {
    if (window.APP_AUTH) {
        return Boolean(window.APP_AUTH.autenticado);
    }

    const sessao = obterSessaoAutenticada();
    return Boolean(sessao && sessao.email);
}

function salvarSessaoAutenticada(usuario) {
    localStorage.setItem(STORAGE_KEY_AUTH, JSON.stringify({
        nome: usuario.nome,
        email: normalizarEmail(usuario.email),
        papel: usuario.papel || "cliente",
        loginEm: new Date().toISOString(),
    }));
}

function encerrarSessaoAutenticada() {
    localStorage.removeItem(STORAGE_KEY_AUTH);
}

function autenticarUsuario(email, senha) {
    const usuario = buscarUsuarioPorEmail(email);
    const senhaNormalizada = String(senha || "").trim();

    if (usuario && String(usuario.senha || "") === senhaNormalizada) {
        salvarSessaoAutenticada(usuario);
        return {
            sucesso: true,
            usuario: obterSessaoAutenticada(),
        };
    }

    return {
        sucesso: false,
        mensagem: "Email ou senha invalidos.",
    };
}

function cadastrarUsuario(nome, email, senha) {
    const nomeNormalizado = String(nome || "").trim();
    const emailNormalizado = normalizarEmail(email);
    const senhaNormalizada = String(senha || "").trim();

    if (!nomeNormalizado || !emailNormalizado || !senhaNormalizada) {
        return {
            sucesso: false,
            mensagem: "Preencha todos os campos para continuar.",
        };
    }

    if (buscarUsuarioPorEmail(emailNormalizado)) {
        return {
            sucesso: false,
            mensagem: "Ja existe uma conta com este email.",
        };
    }

    const usuarios = obterUsuariosLocais();
    const novoUsuario = {
        nome: nomeNormalizado,
        email: emailNormalizado,
        senha: senhaNormalizada,
        papel: "cliente",
    };

    usuarios.push(novoUsuario);
    salvarUsuariosLocais(usuarios);
    salvarSessaoAutenticada(novoUsuario);

    return {
        sucesso: true,
        usuario: obterSessaoAutenticada(),
    };
}

function redirecionarSeNaoAutenticado(destino = obterRotasApp().login) {
    if (!usuarioEstaAutenticado()) {
        window.location.href = destino;
        return false;
    }

    return true;
}

function aplicarLinkDaConta() {
    const linkConta = document.getElementById("link-conta");
    const textoConta = document.getElementById("link-conta-texto");
    const linkContaMobile = document.getElementById("link-conta-mobile");
    const textoContaMobile = document.getElementById("link-conta-mobile-texto");
    const rotas = obterRotasApp();

    if (!linkConta && !linkContaMobile) {
        return;
    }

    function aplicarEstadoLink(link, texto, destino, autenticado, rotuloTitleAutenticado) {
        if (!link) {
            return;
        }

        link.href = destino;
        link.setAttribute("aria-label", autenticado ? "Abrir minha conta" : "Entrar ou acessar conta");
        link.title = autenticado ? rotuloTitleAutenticado : "Minha conta";
        link.dataset.logado = autenticado ? "true" : "false";

        if (texto) {
            const ocultarTextoQuandoAutenticado = texto.dataset.hideWhenAuthenticated === "true";
            texto.hidden = ocultarTextoQuandoAutenticado && autenticado;
            texto.textContent = autenticado ? "Minha conta" : "Entrar";
        }
    }

    if (window.APP_AUTH) {
        const destinoConta = window.APP_AUTH.destinoConta || rotas.login;
        const autenticado = Boolean(window.APP_AUTH.autenticado);
        const rotuloTitleAutenticado = window.APP_AUTH.papel === "admin" ? "Painel administrativo" : "Minha conta";

        aplicarEstadoLink(linkConta, textoConta, destinoConta, autenticado, rotuloTitleAutenticado);
        aplicarEstadoLink(linkContaMobile, textoContaMobile, destinoConta, autenticado, rotuloTitleAutenticado);

        return;
    }

    const sessao = obterSessaoAutenticada();
    const autenticado = Boolean(sessao && sessao.email);
    const destinoConta = autenticado ? obterDestinoPosLogin(sessao?.papel) : rotas.login;
    const rotuloTitleAutenticado = sessao?.papel === "admin" ? "Painel administrativo" : "Minha conta";

    aplicarEstadoLink(linkConta, textoConta, destinoConta, autenticado, rotuloTitleAutenticado);
    aplicarEstadoLink(linkContaMobile, textoContaMobile, destinoConta, autenticado, rotuloTitleAutenticado);
}
