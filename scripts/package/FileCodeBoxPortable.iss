#define MyAppName "FileCodeBox Portable"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "FileCodeBox"
#define MyAppURL "http://127.0.0.1:18080/"

[Setup]
AppId={{FDF103E3-7E8B-4C87-B2AD-69DBB3F14944}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={localappdata}\FileCodeBoxPortable
DisableProgramGroupPage=yes
OutputDir=..\..\dist\installer
OutputBaseFilename=FileCodeBoxPortable-Setup
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
Name: "{autoprograms}\FileCodeBox Portable"; Filename: "{app}\start.cmd"
Name: "{autodesktop}\FileCodeBox Portable"; Filename: "{app}\start.cmd"; Tasks: desktopicon

[Run]
Filename: "{app}\start.cmd"; Description: "Launch FileCodeBox Portable"; Flags: nowait postinstall skipifsilent
