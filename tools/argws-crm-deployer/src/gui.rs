use std::{fs, path::PathBuf};

use eframe::egui;

use super::{generate_stack, validate, VERSION};

#[derive(Clone, Copy, PartialEq, Eq)]
enum Environment { Develop, Production }
#[derive(Clone, Copy, PartialEq, Eq)]
enum Database { Mysql, MariaDb }

struct DeployerApp {
    environment: Environment,
    database: Database,
    version: String,
    output: String,
    force_compose: bool,
    status: String,
    show_browser: bool,
    browser_path: PathBuf,
}

impl Default for DeployerApp {
    fn default() -> Self {
        let current = std::env::current_dir().unwrap_or_else(|_| PathBuf::from("."));
        Self {
            environment: Environment::Production,
            database: Database::Mysql,
            version: VERSION.trim().to_string(),
            output: current.join("argws-crm-deploy").to_string_lossy().into_owned(),
            force_compose: false,
            status: "Escolha o ambiente e a pasta onde os arquivos serão gerados.".into(),
            show_browser: false,
            browser_path: current,
        }
    }
}

impl DeployerApp {
    fn generate(&mut self) {
        let destination = self.output.trim();
        if destination.is_empty() {
            self.status = "Informe a pasta de destino.".into();
            return;
        }
        let environment = match self.environment {
            Environment::Develop => "develop",
            Environment::Production => "production",
        };
        let database = match self.database {
            Database::Mysql => "mysql",
            Database::MariaDb => "mariadb",
        };
        let version = (self.environment == Environment::Production).then_some(self.version.trim());
        self.status = match generate_stack(environment, version, database, &PathBuf::from(destination), self.force_compose) {
            Ok(()) => format!("Arquivos gerados e validados em {destination}."),
            Err(error) => error,
        };
    }

    fn browse_output(&mut self, ctx: &egui::Context) {
        if !self.show_browser { return; }
        let mut open = self.show_browser;
        egui::Window::new("Escolher pasta de destino")
            .open(&mut open)
            .resizable(true)
            .default_width(520.0)
            .show(ctx, |ui| {
                ui.horizontal(|ui| {
                    if ui.button("Pasta acima").clicked() {
                        if let Some(parent) = self.browser_path.parent() {
                            self.browser_path = parent.to_path_buf();
                        }
                    }
                    if ui.button("Usar esta pasta").clicked() {
                        self.output = self.browser_path.to_string_lossy().into_owned();
                        open = false;
                    }
                });
                ui.label(self.browser_path.display().to_string());
                ui.separator();
                egui::ScrollArea::vertical().max_height(300.0).show(ui, |ui| {
                    let mut directories = fs::read_dir(&self.browser_path)
                        .map(|entries| entries.filter_map(Result::ok)
                            .filter(|entry| entry.file_type().map(|kind| kind.is_dir()).unwrap_or(false))
                            .collect::<Vec<_>>())
                        .unwrap_or_default();
                    directories.sort_by_key(|entry| entry.file_name());
                    for entry in directories {
                        let name = entry.file_name().to_string_lossy().into_owned();
                        if ui.button(format!("Pasta: {name}")).clicked() {
                            self.browser_path = entry.path();
                        }
                    }
                });
            });
        self.show_browser = open;
    }
}

impl eframe::App for DeployerApp {
    fn update(&mut self, ctx: &egui::Context, _frame: &mut eframe::Frame) {
        egui::CentralPanel::default().show(ctx, |ui| {
            ui.heading("ARGWS CRM");
            ui.label("Assistente gráfico de implantação");
            ui.add_space(10.0);
            egui::Grid::new("deploy-settings").num_columns(2).spacing([16.0, 12.0]).show(ui, |ui| {
                ui.label("Ambiente");
                egui::ComboBox::from_id_salt("environment")
                    .selected_text(match self.environment {
                        Environment::Develop => "Desenvolvimento",
                        Environment::Production => "Produção",
                    })
                    .show_ui(ui, |ui| {
                        ui.selectable_value(&mut self.environment, Environment::Develop, "Desenvolvimento");
                        ui.selectable_value(&mut self.environment, Environment::Production, "Produção");
                    });
                ui.end_row();
                ui.label("Banco de dados");
                egui::ComboBox::from_id_salt("database")
                    .selected_text(match self.database {
                        Database::Mysql => "MySQL 8.0",
                        Database::MariaDb => "MariaDB 11.4",
                    })
                    .show_ui(ui, |ui| {
                        ui.selectable_value(&mut self.database, Database::Mysql, "MySQL 8.0");
                        ui.selectable_value(&mut self.database, Database::MariaDb, "MariaDB 11.4");
                    });
                ui.end_row();
                if self.environment == Environment::Production {
                    ui.label("Versão da imagem");
                    ui.text_edit_singleline(&mut self.version);
                    ui.end_row();
                }
            });
            ui.add_space(8.0);
            ui.label("Pasta de destino");
            ui.horizontal(|ui| {
                ui.text_edit_singleline(&mut self.output);
                if ui.button("Procurar...").clicked() {
                    self.browser_path = PathBuf::from(self.output.trim());
                    if !self.browser_path.is_dir() {
                        self.browser_path = std::env::current_dir().unwrap_or_else(|_| PathBuf::from("."));
                    }
                    self.show_browser = true;
                }
            });
            ui.checkbox(&mut self.force_compose, "Substituir compose.yaml quando diferir do modelo");
            ui.add_space(8.0);
            ui.horizontal(|ui| {
                if ui.button("Gerar compose.yaml e .env").clicked() { self.generate(); }
                if ui.button("Validar arquivos existentes").clicked() {
                    self.status = match validate(&PathBuf::from(self.output.trim())) {
                        Ok(()) => "Stack válida.".into(),
                        Err(error) => error,
                    };
                }
            });
            ui.add_space(8.0);
            ui.label(&self.status);
            ui.separator();
            ui.label("O .env contém senhas aleatórias e será preservado nas execuções seguintes.");
            ui.label("A interface usa os mesmos modelos e a mesma validação do deployer de terminal.");
        });
        self.browse_output(ctx);
    }
}

pub fn run() -> Result<(), String> {
    let mut options = eframe::NativeOptions::default();
    options.viewport = egui::ViewportBuilder::default()
        .with_inner_size([760.0, 580.0])
        .with_min_inner_size([600.0, 460.0]);
    eframe::run_native(
        "ARGWS CRM Deployer",
        options,
        Box::new(|_context| Ok(Box::new(DeployerApp::default()))),
    )
    .map_err(|error| format!("não foi possível iniciar a interface gráfica: {error}"))
}
