# Apps de check-in — Android e iOS

O check-in da equipe roda em **https://foradafila.com.br/app**. Ele já funciona no navegador do celular e pode
ser **instalado como app sem loja** (PWA). Os apps de loja (Google Play e App Store) são uma "casca" que abre
essa mesma página com permissão de câmera — o projeto está em `apps/checkin/` (Capacitor 6).

Toda mudança no check-in é publicada no site e aparece nos apps na hora, **sem nova versão nas lojas**.

## 0. Sem loja (já funciona hoje)

1. No celular da equipe, abra **foradafila.com.br/app** e entre com o e-mail e a senha da equipe.
2. **Android (Chrome):** menu ⋮ → **Instalar app** (ou "Adicionar à tela inicial").
3. **iPhone (Safari):** botão Compartilhar → **Adicionar à Tela de Início**.
4. O ícone "Check-in" abre em tela cheia, como um app. Na primeira leitura, permita o uso da câmera.

Para a maioria das produtoras isso basta. As lojas servem para a marca aparecer lá e para instalar por busca.

## 1. Preparar o computador (uma vez)

| Para | Precisa de |
| --- | --- |
| Android | Node.js 18+ e **Android Studio** (Windows, Mac ou Linux) |
| iOS | Node.js 18+, um **Mac** com **Xcode** 15+ |

```bash
cd apps/checkin
npm install
```

## 2. Android (Google Play)

1. Criar o projeto Android (só na primeira vez):

```bash
npx cap add android
```

2. Permitir a câmera: em `android/app/src/main/AndroidManifest.xml`, antes de `<application`, acrescente:

```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-feature android:name="android.hardware.camera" android:required="false" />
```

3. Ícone: no Android Studio, botão direito em `app` → **New → Image Asset** → escolha `recursos/icone.png`
   (fundo `#101828`).
4. Abrir e testar num celular ligado no USB:

```bash
npm run android
```

5. Gerar o arquivo da loja: Android Studio → **Build → Generate Signed Bundle / APK → Android App Bundle**.
   Crie a chave de assinatura (guarde o arquivo `.jks` e a senha em lugar seguro: sem ela não dá para atualizar o app).
6. Publicar: crie a conta de desenvolvedor em **play.google.com/console** (taxa única de US$ 25), "Criar app",
   preencha a ficha (nome "Fora da Fila Check-in", descrição, prints, política de privacidade:
   https://foradafila.com.br/termos/privacidade), envie o `.aab` em **Produção** e mande para revisão.
   Conta nova de pessoa física pede um teste fechado com 12 testadores por 14 dias; conta de **empresa (CNPJ)** não.

## 3. iOS (App Store)

1. No Mac, criar o projeto iOS (só na primeira vez):

```bash
npx cap add ios
```

2. Permitir a câmera: em `ios/App/App/Info.plist`, acrescente:

```xml
<key>NSCameraUsageDescription</key>
<string>A câmera é usada para ler o QR Code dos ingressos no check-in.</string>
```

3. Abrir no Xcode:

```bash
npm run ios
```

4. No Xcode: em **Signing & Capabilities**, escolha o time da conta Apple; em **App Icons**, use `recursos/icone.png`
   (1024×1024 — gere a partir dele).
5. Testar num iPhone ligado no cabo (Product → Run).
6. Publicar: conta **Apple Developer** (US$ 99/ano; para empresa precisa do D-U-N-S da BH Software), crie o app em
   **appstoreconnect.apple.com**, no Xcode **Product → Archive → Distribute App → App Store Connect**, preencha a ficha
   (prints, privacidade, login de teste para o revisor — crie uma conta de equipe só para isso) e envie para revisão.

**Atenção na revisão da Apple:** apps que só abrem um site podem ser recusados (regra 4.2, "funcionalidade mínima").
Ajuda muito: descrever o app como ferramenta de portaria (leitor de QR com câmera, contagem em tempo real, lista de
participantes) e mandar um login de teste com um evento de demonstração aberto. Se a Apple recusar, o caminho é o
PWA (item 0), que funciona no iPhone do mesmo jeito.

## 4. Segurança do check-in

- O QR do ingresso **não é um link**: é `FF1.<código>.<assinatura>`. A câmera comum do celular só mostra o texto.
- A entrada só é registrada pelo app/painel com **login da equipe** e permissão **checkin.realizar** (Equipe → níveis de acesso).
- Só no **dia do evento**, da **abertura dos portões** (no mesmo dia; pode ser 1 ou 2 horas antes do início) até o **término**.
- Cada leitura fica no histórico (`checkin_log`: quem leu, quando, resultado). Dois portões lendo o mesmo QR ao mesmo tempo: só um passa.
