# ARGWS CRM Deployer GUI

A interface usa Python e Tkinter, com controles de desktop do sistema operacional. Ela não inicia OpenGL, WGPU, Vulkan ou outro backend de aceleração gráfica.

O executável gráfico chama o Deployer CLI Rust que acompanha o pacote. O núcleo em Rust continua responsável por gerar e validar as configurações; a GUI não replica essas regras.

## Uso

Baixe e extraia o pacote correspondente ao computador:

- Windows x64: argws-crm-deployer-gui-win-x64.zip
- Linux x64: argws-crm-deployer-gui-linux-x64.zip

Mantenha os dois arquivos extraídos na mesma pasta. Inicie o executável gráfico por clique duplo. O pacote também contém o CLI independente, que pode ser usado em terminal e em servidores sem desktop.

No Linux, a sessão precisa oferecer um display X11 ou XWayland. O Deployer CLI continua sem dependência de interface gráfica.
