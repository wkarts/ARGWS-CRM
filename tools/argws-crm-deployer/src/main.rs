use std::{
    env,
    fs::OpenOptions,
    io::{self, BufRead, Write},
    path::{Path, PathBuf},
    process::ExitCode,
};
#[cfg(any(unix, test))]
use std::fs;

mod core;

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum FailureKind {
    Usage,
    Operation,
}

#[derive(Debug)]
struct CliFailure {
    kind: FailureKind,
    message: String,
}

impl CliFailure {
    fn usage(message: impl Into<String>) -> Self {
        Self { kind: FailureKind::Usage, message: message.into() }
    }

    fn operation(message: impl Into<String>) -> Self {
        Self { kind: FailureKind::Operation, message: message.into() }
    }

    fn exit_code(&self) -> u8 {
        match self.kind {
            FailureKind::Usage => 2,
            FailureKind::Operation => 1,
        }
    }
}

fn help() {
    println!(
        "ARGWS CRM Deployer CLI\n\
         Uso:\n\
           argws-crm-deployer interactive\n\
           argws-crm-deployer generate --environment develop|production --output DIR [--database mysql|mariadb] [--version X.Y.Z] [--force]\n\
           argws-crm-deployer validate --directory DIR\n\
           argws-crm-deployer version\n\
           argws-crm-deployer list\n\
         Opções globais: --log-file FILE\n\
         A interface gráfica é distribuída separadamente e usa este mesmo CLI como backend."
    );
}

fn option_value(args: &[String], name: &str) -> Result<String, CliFailure> {
    let Some(index) = args.iter().position(|arg| arg == name) else {
        return Err(CliFailure::usage(format!("{name} é obrigatório")));
    };
    let Some(value) = args.get(index + 1).filter(|value| !value.starts_with("--")) else {
        return Err(CliFailure::usage(format!("informe um valor para {name}")));
    };
    Ok(value.clone())
}

fn validate_options(args: &[String], value_options: &[&str], flag_options: &[&str]) -> Result<(), CliFailure> {
    let mut index = 1;
    while index < args.len() {
        let option = args[index].as_str();
        if flag_options.contains(&option) {
            index += 1;
        } else if value_options.contains(&option) {
            if args.get(index + 1).is_none_or(|value| value.starts_with("--")) {
                return Err(CliFailure::usage(format!("informe um valor para {option}")));
            }
            index += 2;
        } else {
            return Err(CliFailure::usage("opção ou argumento não reconhecido"));
        }
    }
    Ok(())
}

fn prompt<R: BufRead, W: Write>(
    reader: &mut R,
    writer: &mut W,
    label: &str,
    default: &str,
) -> Result<String, CliFailure> {
    write!(writer, "{label} [{default}]: ").map_err(|error| CliFailure::operation(error.to_string()))?;
    writer.flush().map_err(|error| CliFailure::operation(error.to_string()))?;

    let mut value = String::new();
    let bytes = reader.read_line(&mut value).map_err(|error| CliFailure::operation(error.to_string()))?;
    if bytes == 0 {
        return Err(CliFailure::usage("entrada encerrada; modo interativo cancelado"));
    }
    let value = value.trim();
    Ok(if value.is_empty() { default.to_string() } else { value.to_string() })
}

fn run_interactive<R: BufRead, W: Write>(reader: &mut R, writer: &mut W) -> Result<(), CliFailure> {
    let environment = prompt(reader, writer, "Ambiente (develop/production)", "production")?;
    let database = prompt(reader, writer, "Banco (mysql/mariadb)", "mysql")?;
    let version = if environment == "production" {
        Some(prompt(reader, writer, "Versão da imagem", core::VERSION.trim())?)
    } else {
        None
    };
    let output = prompt(reader, writer, "Pasta de destino", "argws-crm-deploy")?;
    let force = prompt(reader, writer, "Substituir compose.yaml diferente? (s/N)", "n")?;
    let force = matches!(force.to_ascii_lowercase().as_str(), "s" | "sim" | "y" | "yes");

    core::generate_stack(&environment, version.as_deref(), &database, Path::new(&output), force)
        .map_err(CliFailure::operation)?;
    writeln!(writer, "Stack preparada e validada em {output}.")
        .map_err(|error| CliFailure::operation(error.to_string()))
}

fn run_cli(args: &[String]) -> Result<(), CliFailure> {
    let command = args.first().map(String::as_str).unwrap_or("help");
    match command {
        "help" | "--help" | "-h" => {
            if args.len() > 1 {
                return Err(CliFailure::usage("help não recebe argumentos"));
            }
            help();
            Ok(())
        }
        "version" => {
            if args.len() > 1 {
                return Err(CliFailure::usage("version não recebe argumentos"));
            }
            println!("{}", core::VERSION.trim());
            Ok(())
        }
        "list" => {
            if args.len() > 1 {
                return Err(CliFailure::usage("list não recebe argumentos"));
            }
            println!("Ambientes: develop, production; bancos: mysql, mariadb");
            Ok(())
        }
        "interactive" | "--interactive" => {
            if args.len() > 1 {
                return Err(CliFailure::usage("interactive não recebe argumentos"));
            }
            let stdin = io::stdin();
            let stdout = io::stdout();
            run_interactive(&mut stdin.lock(), &mut stdout.lock())
        }
        "validate" => {
            validate_options(args, &["--directory"], &[])?;
            let directory = option_value(args, "--directory")?;
            core::validate(Path::new(&directory)).map_err(CliFailure::operation)?;
            println!("Stack válida: {directory}");
            Ok(())
        }
        "generate" => {
            validate_options(
                args,
                &["--environment", "--output", "--database", "--version"],
                &["--force"],
            )?;
            let environment = option_value(args, "--environment")?;
            let output = PathBuf::from(option_value(args, "--output")?);
            let database = args
                .iter()
                .position(|arg| arg == "--database")
                .and_then(|index| args.get(index + 1))
                .cloned()
                .unwrap_or_else(|| "mysql".to_string());
            let version = args
                .iter()
                .position(|arg| arg == "--version")
                .and_then(|index| args.get(index + 1))
                .map(String::as_str);
            let force = args.iter().any(|arg| arg == "--force");
            core::generate_stack(&environment, version, &database, &output, force)
                .map_err(CliFailure::operation)?;
            println!("Stack preparada e validada em {}", output.display());
            Ok(())
        }
        _ => Err(CliFailure::usage("comando esperado: interactive, generate, validate, version, list ou help")),
    }
}

fn remove_log_option(args: &[String]) -> Result<(Vec<String>, Option<PathBuf>), CliFailure> {
    let mut filtered = Vec::with_capacity(args.len());
    let mut log_path = None;
    let mut index = 0;
    while index < args.len() {
        if args[index] == "--log-file" {
            if log_path.is_some() {
                return Err(CliFailure::usage("--log-file pode ser informado uma vez"));
            }
            let Some(path) = args.get(index + 1).filter(|value| !value.starts_with("--")) else {
                return Err(CliFailure::usage("informe o caminho de --log-file"));
            };
            log_path = Some(PathBuf::from(path));
            index += 2;
        } else {
            filtered.push(args[index].clone());
            index += 1;
        }
    }
    Ok((filtered, log_path))
}

fn log_command(args: &[String]) -> &'static str {
    match args.first().map(String::as_str).unwrap_or("help") {
        "generate" | "interactive" | "--interactive" => "generate",
        "validate" => "validate",
        "version" => "version",
        "list" => "list",
        _ => "help",
    }
}

fn append_operation_log(path: &Path, command: &str, succeeded: bool) -> io::Result<()> {
    let mut options = OpenOptions::new();
    options.create(true).append(true);
    #[cfg(unix)]
    {
        use std::os::unix::fs::OpenOptionsExt;
        options.mode(0o600);
    }
    let mut file = options.open(path)?;
    #[cfg(unix)]
    {
        use std::os::unix::fs::PermissionsExt;
        fs::set_permissions(path, fs::Permissions::from_mode(0o600))?;
    }
    append_operation_log_to(&mut file, command, succeeded)
}

fn main() -> ExitCode {
    let raw_args: Vec<String> = env::args().skip(1).collect();
    let (args, log_path) = match remove_log_option(&raw_args) {
        Ok(result) => result,
        Err(error) => {
            eprintln!("Erro: {}", error.message);
            return ExitCode::from(error.exit_code());
        }
    };
    let command = log_command(&args);
    let result = run_cli(&args);
    if let Some(path) = log_path {
        if let Err(error) = append_operation_log(&path, command, result.is_ok()) {
            eprintln!("Não foi possível gravar o log operacional: {error}");
            return ExitCode::FAILURE;
        }
    }
    match result {
        Ok(()) => ExitCode::SUCCESS,
        Err(error) => {
            eprintln!("Erro: {}", error.message);
            ExitCode::from(error.exit_code())
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::{io::Cursor, time::{SystemTime, UNIX_EPOCH}};

    #[test]
    fn interactive_mode_uses_the_shared_core_and_creates_a_valid_stack() {
        let suffix = SystemTime::now().duration_since(UNIX_EPOCH).unwrap().as_nanos();
        let output = env::temp_dir().join(format!("argws-deployer-interactive-{suffix}"));
        let input = format!("develop\nmariadb\n{}\nn\n", output.display());
        let mut reader = Cursor::new(input);
        let mut transcript = Vec::new();

        run_interactive(&mut reader, &mut transcript).unwrap();

        core::validate(&output).unwrap();
        assert!(String::from_utf8(transcript).unwrap().contains("Stack preparada e validada"));
        fs::remove_dir_all(output).unwrap();
    }

    #[test]
    fn operation_log_contains_only_fixed_operation_metadata() {
        let mut output = Vec::new();
        append_operation_log_to(&mut output, "generate", false).unwrap();
        let line = String::from_utf8(output).unwrap();
        assert_eq!(line, "outcome=failure command=generate\n");
        assert!(!line.contains("password"));
        assert!(!line.contains("token"));
    }

    #[test]
    fn version_command_reports_the_embedded_semver() {
        assert_eq!(log_command(&["version".into()]), "version");
        assert_eq!(core::VERSION.trim().split('.').count(), 3);
    }

    #[test]
    fn usage_errors_use_a_distinct_exit_code() {
        assert_eq!(CliFailure::usage("uso").exit_code(), 2);
        assert_eq!(CliFailure::operation("operação").exit_code(), 1);
    }
}

fn append_operation_log_to(writer: &mut impl Write, command: &str, succeeded: bool) -> io::Result<()> {
    writeln!(
        writer,
        "outcome={} command={}",
        if succeeded { "success" } else { "failure" },
        command
    )
}
