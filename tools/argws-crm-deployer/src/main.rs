use std::{env, fs::{self, OpenOptions}, io::Write, path::{Path, PathBuf}};
#[cfg(unix)]
use std::{fs::File, io::Read};

#[cfg(feature = "gui")]
mod gui;
const DEV_COMPOSE: &str = include_str!("../../../deploy/develop/compose.yaml");
const PROD_COMPOSE: &str = include_str!("../../../deploy/production/compose.yaml");
const DEV_ENV: &str = include_str!("../../../deploy/develop/.env.example");
const PROD_ENV: &str = include_str!("../../../deploy/production/.env.example");
const VERSION: &str = include_str!("../../../VERSION");
const MYSQL: &str = "ghcr.io/wkarts/argws-crm-mysql:8.0";
const MARIADB: &str = "ghcr.io/wkarts/argws-crm-mariadb:11.4";

#[cfg(windows)]
#[link(name = "bcrypt")]
extern "system" {
    fn BCryptGenRandom(a: *mut std::ffi::c_void, b: *mut u8, n: u32, flags: u32) -> i32;
}

fn random_hex() -> Result<String, String> {
    let mut bytes = [0u8; 32];
    #[cfg(unix)]
    File::open("/dev/urandom").and_then(|mut f| f.read_exact(&mut bytes))
        .map_err(|e| format!("aleatoriedade segura indisponível: {e}"))?;
    #[cfg(windows)]
    {
        let status = unsafe { BCryptGenRandom(std::ptr::null_mut(), bytes.as_mut_ptr(), 32, 2) };
        if status != 0 { return Err(format!("BCryptGenRandom falhou: {status}")); }
    }
    let mut out = String::with_capacity(64);
    for byte in bytes { out.push_str(&format!("{byte:02x}")); }
    Ok(out)
}

fn arg(args: &[String], name: &str) -> Option<String> {
    args.windows(2).find(|pair| pair[0] == name).map(|pair| pair[1].clone())
}
fn semver(v: &str) -> bool {
    let p: Vec<_> = v.split('.').collect();
    p.len() == 3 && p.iter().all(|x| !x.is_empty() && x.bytes().all(|b| b.is_ascii_digit()))
}
fn set(contents: &str, key: &str, value: &str) -> String {
    let prefix = format!("{key}=");
    let mut found = false;
    let mut lines = Vec::new();
    for line in contents.lines() {
        if line.starts_with(&prefix) { lines.push(format!("{key}={value}")); found = true; }
        else { lines.push(line.to_string()); }
    }
    if !found { lines.push(format!("{key}={value}")); }
    format!("{}\n", lines.join("\n"))
}
fn get(contents: &str, key: &str) -> Option<String> {
    let prefix = format!("{key}=");
    contents.lines().find_map(|line| line.strip_prefix(&prefix).map(str::to_string))
}
fn secure_write(path: &Path, contents: &str) -> Result<(), String> {
    let mut options = OpenOptions::new();
    options.write(true).create_new(true);
    #[cfg(unix)]
    { use std::os::unix::fs::OpenOptionsExt; options.mode(0o600); }
    let mut file = options.open(path).map_err(|e| format!("não foi possível criar {}: {e}", path.display()))?;
    file.write_all(contents.as_bytes()).map_err(|e| format!("não foi possível escrever .env: {e}"))
}
fn write_compose(path: &Path, contents: &str, force: bool) -> Result<(), String> {
    if path.exists() {
        let old = fs::read_to_string(path).map_err(|e| e.to_string())?;
        if old == contents { return Ok(()); }
        if !force { return Err("compose.yaml existente difere do modelo; use --force".into()); }
    }
    fs::write(path, contents).map_err(|e| format!("não foi possível escrever compose.yaml: {e}"))
}
fn validate(dir: &Path) -> Result<(), String> {
    let compose = fs::read_to_string(dir.join("compose.yaml")).map_err(|_| "compose.yaml ausente")?;
    let env = fs::read_to_string(dir.join(".env")).map_err(|_| ".env ausente")?;
    for key in ["ARGWS_CRM_IMAGE","ARGWS_CRM_DATABASE_IMAGE","ARGWS_HTTP_BIND","ARGWS_HTTP_PORT","MYSQL_DATABASE","MYSQL_USER","MYSQL_PASSWORD","MYSQL_ROOT_PASSWORD","TZ"] {
        let value = get(&env, key).ok_or_else(|| format!("variável ausente: {key}"))?;
        if value.is_empty() || value.starts_with("CHANGE_ME") { return Err(format!("valor de exemplo pendente: {key}")); }
    }
    let image = get(&env, "ARGWS_CRM_IMAGE").unwrap();
    let tag = image.rsplit(':').next().unwrap_or("");
    if !image.starts_with("ghcr.io/wkarts/argws-crm:") || (tag != "develop" && !semver(tag)) {
        return Err("use a imagem oficial com develop ou SemVer X.Y.Z".into());
    }
    let db = get(&env, "ARGWS_CRM_DATABASE_IMAGE").unwrap();
    if db != MYSQL && db != MARIADB { return Err("banco deve usar a imagem MySQL ou MariaDB do GHCR".into()); }
    if get(&env,"MYSQL_PASSWORD") == get(&env,"MYSQL_ROOT_PASSWORD") { return Err("as senhas MySQL devem ser diferentes".into()); }
    for key in ["ARGWS_CRM_IMAGE","ARGWS_CRM_DATABASE_IMAGE","MYSQL_PASSWORD"] {
        if !compose.contains(&format!("{}{{{}", "$", key)) { return Err(format!("compose não usa {key}")); }
    }
    #[cfg(unix)]
    {
        use std::os::unix::fs::PermissionsExt;
        let mode = fs::metadata(dir.join(".env")).map_err(|e|e.to_string())?.permissions().mode() & 0o777;
        if mode & 0o077 != 0 { return Err("permissão insegura no .env; use chmod 600".into()); }
    }
    Ok(())
}

fn generate_stack(environment: &str, version: Option<&str>, database: &str, output: &Path, force: bool) -> Result<(), String> {
    let (compose, example, default_tag) = match environment {
        "develop" => (DEV_COMPOSE, DEV_ENV, "develop"),
        "production" => (PROD_COMPOSE, PROD_ENV, VERSION.trim()),
        _ => return Err("environment deve ser develop ou production".into()),
    };
    let tag = version.unwrap_or(default_tag);
    if environment == "production" && !semver(tag) { return Err("produção exige versão SemVer X.Y.Z".into()); }
    let db_image = match database {
        "mysql" => MYSQL,
        "mariadb" => MARIADB,
        _ => return Err("database deve ser mysql ou mariadb".into()),
    };
    fs::create_dir_all(output).map_err(|e| format!("não foi possível criar a pasta: {e}"))?;
    write_compose(&output.join("compose.yaml"), compose, force)?;
    let env_path = output.join(".env");
    if !env_path.exists() {
        let mut contents = set(example, "ARGWS_CRM_IMAGE", &format!("ghcr.io/wkarts/argws-crm:{tag}"));
        contents = set(&contents, "ARGWS_CRM_DATABASE_IMAGE", db_image);
        contents = set(&contents, "MYSQL_PASSWORD", &random_hex()?);
        contents = set(&contents, "MYSQL_ROOT_PASSWORD", &random_hex()?);
        secure_write(&env_path, &contents)?;
    }
    validate(output)?;
    Ok(())
}

fn main_result() -> Result<(), String> {
    let args: Vec<String> = env::args().skip(1).collect();
    let cmd = args.first().map(String::as_str).unwrap_or("help");
    if cmd == "list" {
        println!("Ambientes: develop, production; bancos: mysql, mariadb");
        return Ok(());
    }
    if cmd == "help" || cmd == "--help" || cmd == "-h" {
        println!("argws-crm-deployer generate --environment develop|production --output DIR [--database mysql|mariadb] [--version X.Y.Z] [--force]\nargws-crm-deployer validate --directory DIR\nargws-crm-deployer list");
        return Ok(());
    }
    if cmd == "validate" {
        let dir = arg(&args,"--directory").ok_or("--directory é obrigatório")?;
        validate(Path::new(&dir))?;
        println!("Stack válida: {dir}");
        return Ok(());
    }
    if cmd != "generate" { return Err("comando esperado: list, generate, validate ou help".into()); }
    let environment = arg(&args, "--environment").ok_or("--environment é obrigatório")?;
    let version = arg(&args, "--version");
    let database = arg(&args, "--database").unwrap_or_else(|| "mysql".to_string());
    let output = PathBuf::from(arg(&args, "--output").ok_or("--output é obrigatório")?);
    generate_stack(&environment, version.as_deref(), &database, &output, args.iter().any(|x| x == "--force"))?;
    println!("Stack preparada e validada em {}", output.display());
    Ok(())
}
fn main() {
    #[cfg(feature = "gui")]
    {
        let args: Vec<String> = env::args().skip(1).collect();
        if args.is_empty() || matches!(args.first().map(String::as_str), Some("gui" | "--gui")) {
            if let Err(error) = gui::run() { eprintln!("Erro: {error}"); std::process::exit(1); }
            return;
        }
    }
    if let Err(error) = main_result() { eprintln!("Erro: {error}"); std::process::exit(1); }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test] fn stable_version_is_semver() { assert!(semver("3.4.2")); assert!(!semver("latest")); }
    #[test] fn environments_include_database_and_runtime() {
        assert!(DEV_COMPOSE.contains("database:"));
        assert!(PROD_COMPOSE.contains("database_data:"));
        assert!(DEV_ENV.contains("argws-crm:develop"));
    }
    #[test] fn env_replacement_preserves_other_values() {
        let value = set("A=1\nB=2\n","A","3");
        assert_eq!(get(&value,"B").as_deref(),Some("2"));
        assert_eq!(get(&value,"A").as_deref(),Some("3"));
    }
}
