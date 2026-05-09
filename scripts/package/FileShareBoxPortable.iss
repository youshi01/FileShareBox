#define MyAppName "FileShareBox Portable"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "FileShareBox"
#define MyAppURL "http://127.0.0.1:18080/"

[Setup]
AppId={{FDF103E3-7E8B-4C87-B2AD-69DBB3F14944}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={localappdata}\FileShareBoxPortable
DisableProgramGroupPage=yes
OutputDir=..\..\dist\installer
OutputBaseFilename=FileShareBoxPortable-Setup
Compression=lzma
SolidCompression=yes
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64
PrivilegesRequired=lowest

[Languages]
Name: "default"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create desktop icon"; GroupDescription: "Additional icons:"

[Files]
Source: "..\..\dist\portable\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{autoprograms}\FileShareBox Portable"; Filename: "{app}\start.cmd"
Name: "{autodesktop}\FileShareBox Portable"; Filename: "{app}\start.cmd"; Tasks: desktopicon

[Run]
Filename: "{app}\start.cmd"; Description: "Launch FileShareBox Portable"; Flags: nowait postinstall skipifsilent
