# AWS Assume Role Plugin for Composer

**AWS Assume Role Plugin for Composer** は、Composerを使用してAWS IAMロールを簡単にアサインし、一時的な認証情報を取得して環境変数として設定するためのプラグインです。このプラグインにより、AWSの一時的な認証情報を安全かつ効率的に管理できます。

## Table of Contents

- [English](#english)
    - [Introduction](#introduction)
    - [Features](#features)
    - [Installation](#installation)
    - [Configuration](#configuration)
    - [Usage](#usage)
        - [Set Environment Variables (Default Behavior)](#set-environment-variables-default-behavior)
        - [Execute a General Command](#execute-a-general-command)
        - [Specify MFA Code Directly](#specify-mfa-code-directly)
    - [License](#license)
- [日本語](#日本語)
    - [概要](#概要)
    - [特徴](#特徴-1)
    - [インストール](#インストール-1)
    - [設定](#設定)
    - [使い方](#使い方-1)
        - [環境変数として設定（デフォルト動作）](#環境変数として設定デフォルト動作)
        - [一般的なコマンドの実行](#一般的なコマンドの実行-1)
        - [MFAコードを直接指定](#mfacodeを直接指定)
    - [ライセンス](#ライセンス-1)

---

## English

### Introduction

The **AWS Assume Role Plugin for Composer** allows you to assume an AWS IAM role with Multi-Factor Authentication (MFA) and execute Composer commands or other shell commands using temporary AWS credentials. This enhances security by ensuring that sensitive operations are performed with temporary, limited-privilege credentials.

### Features

- **Assume AWS IAM Role with MFA**: Securely assume roles requiring MFA.
- **Execute General Commands**: Run any shell command with the assumed role's credentials.
- **Set Environment Variables (Default Behavior)**: Output `export` commands to set temporary AWS credentials as environment variables.
- **Specify MFA Code Directly**: Provide the MFA code via the `--code` option to bypass the prompt.
- **Flexible Configuration**: Supports both AWS profiles and manual input for role and MFA configurations.
- **Comprehensive Error Handling**: Provides detailed error messages to assist in troubleshooting.

### Installation

You can install the plugin using Composer. Run the following command in your project's root directory:

```
composer require --dev pj8/aws-assume-role-plugin
```

After installation, ensure that Composer recognizes the plugin. You might need to enable it globally or within your project, depending on your setup.

### Configuration

#### AWS Profiles

The plugin relies on AWS profiles defined in your `~/.aws/config` file. Ensure that you have a profile set up with the necessary `role_arn` and `mfa_serial`. Here's an example configuration:

```
[profile your-profile]
role_arn = arn:aws:iam::123456789012:role/YourRole
mfa_serial = arn:aws:iam::123456789012:mfa/YourMFADevice
region = us-east-1
```

#### Manual Configuration

If you prefer not to use an AWS profile, the plugin allows you to manually input the necessary details when executing the command.

### Usage

The plugin provides the `assume-role` command with the following options:

- `--aws-profile`: Specifies the AWS CLI profile to use.
- `--command`: Executes a general shell command with the assumed credentials.
- `--code`: Provides the MFA code directly, bypassing the prompt.

**Note**: You can use `--command` and `--code` simultaneously. The `--code` option is only relevant if MFA is required.

If you do not specify the `--command` option, the plugin will output the temporary AWS credentials as `export` commands for setting environment variables.

#### Set Environment Variables (Default Behavior)

To assume a role using a specific AWS profile and set the temporary credentials as environment variables in your current shell:

```
eval $(composer assume-role --aws-profile=your-profile)
```

**Explanation:**

- The command outputs `export` statements for `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_SESSION_TOKEN`.
- The `eval` command executes these statements in the current shell, setting the environment variables.

**Example:**

```
$ eval $(composer assume-role --aws-profile=my-aws-profile)
export AWS_ACCESS_KEY_ID='ASIA...'
export AWS_SECRET_ACCESS_KEY='secret'
export AWS_SESSION_TOKEN='token'

$ echo $AWS_ACCESS_KEY_ID
ASIA...

$ echo $AWS_SECRET_ACCESS_KEY
secret

$ echo $AWS_SESSION_TOKEN
token
```

#### Execute a General Command

To assume a role and execute a general shell command using the temporary credentials:

```
composer assume-role --aws-profile=your-profile --command="php artisan migrate"
```

**Explanation:**

- The command assumes the specified AWS IAM role with MFA.
- Executes `php artisan migrate` using the temporary credentials.

**Example:**

```
$ composer assume-role --aws-profile=my-aws-profile --command="php artisan migrate"
Executing command: php artisan migrate
# Output from php artisan migrate will be displayed here
```

#### Specify MFA Code Directly

To assume a role and provide the MFA code directly via the `--code` option, bypassing the prompt:

```
composer assume-role --aws-profile=your-profile --code=123456
```

**Explanation:**

- The command uses the provided MFA code (`123456`) instead of prompting for it.
- Outputs `export` commands for setting environment variables.

**Example:**

```
$ composer assume-role --aws-profile=my-aws-profile --code=123456
export AWS_ACCESS_KEY_ID='ASIA...'
export AWS_SECRET_ACCESS_KEY='secret'
export AWS_SESSION_TOKEN='token'
```

You can then evaluate the output to set the environment variables:

```
eval $(composer assume-role --aws-profile=my-aws-profile --code=123456)
```

### License

This project is licensed under the [MIT License](LICENSE).

---

## 日本語

### 概要

**AWS Assume Role Plugin for Composer** は、AWS IAM ロールを MFA（多要素認証）付きでアサインし、一時的なAWS認証情報を使用してComposerコマンドやその他のシェルコマンドを実行するプラグインです。これにより、機密性の高い操作を一時的で限定的な権限の認証情報を使用して実行することで、セキュリティが向上します。

### 特徴

- **MFA付きのAWS IAMロールのアサイン**: MFAが必要なロールを安全にアサインします。
- **一般的なコマンドの実行**: アサインされたロールの認証情報を使用して任意のシェルコマンドを実行します。
- **環境変数として設定（デフォルト動作）**: 一時的なAWS認証情報を環境変数として設定するための `export` コマンドを出力します。
- **MFAコードを直接指定**: `--code` オプションを使用してMFAコードを直接指定し、プロンプトをスキップします。
- **柔軟な設定**: AWSプロファイルの使用と、ロールおよびMFA設定の手動入力の両方をサポートします。
- **包括的なエラーハンドリング**: トラブルシューティングを支援する詳細なエラーメッセージを提供します。

### インストール

プラグインはComposerを使用してインストールできます。プロジェクトのルートディレクトリで以下のコマンドを実行してください：

```
composer require --dev pj8/aws-assume-role-plugin
```

インストール後、Composerがプラグインを認識していることを確認してください。設定に応じて、グローバルまたはプロジェクト内で有効化が必要になる場合があります。

### 設定

#### AWSプロファイル

プラグインは `~/.aws/config` ファイルに定義されたAWSプロファイルを使用します。必要な `role_arn` と `mfa_serial` を持つプロファイルが設定されていることを確認してください。以下は設定例です：

```
[profile your-profile]
role_arn = arn:aws:iam::123456789012:role/YourRole
mfa_serial = arn:aws:iam::123456789012:mfa/YourMFADevice
region = us-east-1
```

#### 手動設定

AWSプロファイルを使用しない場合、コマンド実行時に必要な詳細情報を手動で入力することも可能です。

### 使い方

プラグインは以下のオプションを持つ `assume-role` コマンドを提供します：

- `--aws-profile`: 使用するAWS CLIプロファイルを指定します。
- `--command`: アサインされたロールの認証情報を使用して一般的なシェルコマンドを実行します。
- `--code`: MFAコードを直接指定し、プロンプトをスキップします。

**注意**: `--command` と `--code` は同時に使用可能です。`--code` オプションはMFAが必要な場合にのみ関連します。

`--command` オプションを指定しない場合、プラグインは取得した一時的なAWS認証情報を環境変数として設定するための `export` コマンドのみを出力します。

#### 環境変数として設定（デフォルト動作）

特定のAWSプロファイルを使用してロールをアサインし、一時的な認証情報を環境変数として設定するための `export` コマンドのみを出力します。この出力を `eval` コマンドで評価することで、環境変数を設定します。

```
eval $(composer assume-role --aws-profile=your-profile)
```

**例:**

```
$ eval $(composer assume-role --aws-profile=my-aws-profile)
export AWS_ACCESS_KEY_ID='ASIA...'
export AWS_SECRET_ACCESS_KEY='secret'
export AWS_SESSION_TOKEN='token'

$ echo $AWS_ACCESS_KEY_ID
ASIA...

$ echo $AWS_SECRET_ACCESS_KEY
secret

$ echo $AWS_SESSION_TOKEN
token
```

#### 一般的なコマンドの実行

ロールをアサインし、任意のシェルコマンドを実行するには以下のようにします：

```
composer assume-role --aws-profile=your-profile --command="php artisan migrate"
```

**例:**

```
$ composer assume-role --aws-profile=my-aws-profile --command="php artisan migrate"
Executing command: php artisan migrate
# コマンドの出力がここに表示されます
```

#### MFAコードを直接指定

`--code` オプションを使用して、MFAコードを直接指定することができます。これにより、プロンプトでMFAコードを入力する必要がなくなります。

```
composer assume-role --aws-profile=your-profile --code=123456
```

**例:**

```
$ composer assume-role --aws-profile=my-aws-profile --code=123456
export AWS_ACCESS_KEY_ID='ASIA...'
export AWS_SECRET_ACCESS_KEY='secret'
export AWS_SESSION_TOKEN='token'
```

その後、以下のように `eval` コマンドで環境変数を設定します：

```
eval $(composer assume-role --aws-profile=my-aws-profile --code=123456)
```

### License

このプロジェクトは [MITライセンス](LICENSE) の下でライセンスされています。
