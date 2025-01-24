# AWS Assume Role Plugin for Composer

## Table of Contents

- [English](#english)
    - [Introduction](#introduction)
    - [Features](#features)
    - [Installation](#installation)
    - [Configuration](#configuration)
    - [Usage](#usage)
        - [Execute a Composer Command](#execute-a-composer-command)
        - [Execute a General Command](#execute-a-general-command)
        - [Output Credentials as JSON](#output-credentials-as-json)
    - [Environment Variables](#environment-variables)
    - [Error Handling](#error-handling)
    - [License](#license)
- [日本語](#日本語)
    - [概要](#概要)
    - [特徴](#特徴)
    - [インストール](#インストール-1)
    - [設定](#設定)
    - [使い方](#使い方)
        - [Composer コマンドの実行](#composer-コマンドの実行)
        - [一般的なコマンドの実行](#一般的なコマンドの実行)
        - [認証情報の JSON 出力](#認証情報の-json-出力)
    - [環境変数](#環境変数)
    - [エラーハンドリング](#エラーハンドリング)
    - [ライセンス](#ライセンス)

---

## English

### Introduction

The **AWS Assume Role Plugin for Composer** allows you to assume an AWS IAM role with Multi-Factor Authentication (MFA) and execute Composer commands or other shell commands using temporary AWS credentials. This enhances security by ensuring that sensitive operations are performed with temporary, limited-privilege credentials.

### Features

- **Assume AWS IAM Role with MFA**: Securely assume roles requiring MFA.
- **Execute Composer Commands**: Run Composer commands (`composer install`, `composer update`, etc.) with temporary credentials.
- **Execute General Commands**: Run any shell command with the assumed role's credentials.
- **Output Credentials as JSON**: Retrieve temporary AWS credentials in JSON format for use in other tools or scripts.
- **Flexible Configuration**: Supports both AWS profiles and manual input for role and MFA configurations.
- **Comprehensive Error Handling**: Provides detailed error messages to assist in troubleshooting.

### Installation

You can install the plugin using Composer. Run the following command in your project's root directory:

[code=bash]
composer require --dev pj8/aws-assume-role-plugin
[/code]

After installation, ensure that Composer recognizes the plugin. You might need to enable it globally or within your project, depending on your setup.

### Configuration

#### AWS Profiles

The plugin relies on AWS profiles defined in your `~/.aws/config` file. Ensure that you have a profile set up with the necessary `role_arn` and `mfa_serial`. Here's an example configuration:

[code=ini]
[profile your-profile]
role_arn = arn:aws:iam::123456789012:role/YourRole
mfa_serial = arn:aws:iam::123456789012:mfa/YourMFADevice
region = us-east-1
[/code]

#### Manual Configuration

If you prefer not to use an AWS profile, the plugin allows you to manually input the necessary details when executing the command.

### Usage

The plugin provides the `assume-role` command with the following options:

- `--aws-profile`: Specifies the AWS CLI profile to use.
- `--composer-command`: Executes a Composer command with the assumed credentials.
- `--command`: Executes a general shell command with the assumed credentials.

**Note**: You cannot use `--composer-command` and `--command` simultaneously. If both are specified, an error message will be displayed and the plugin will exit.

If neither option is specified, the plugin will output the temporary AWS credentials in JSON format.

#### Execute a Composer Command

To assume a role using a specific AWS profile and execute a Composer command:

[code=bash]
composer assume-role --aws-profile=your-profile --composer-command=install
[/code]

This command assumes the specified AWS IAM role with MFA and runs `composer install` using the temporary credentials.

#### Execute a General Command

To assume a role and execute a general shell command:

[code=bash]
composer assume-role --aws-profile=your-profile --command="php artisan migrate"
[/code]

This command assumes the specified AWS IAM role with MFA and runs `php artisan migrate` using the temporary credentials.

#### Output Credentials as JSON

If you do not specify either `--composer-command` or `--command`, the plugin will output the temporary AWS credentials in JSON format:

[code=bash]
composer assume-role --aws-profile=your-profile
[/code]

**Sample Output**:

[code=json]
{
"AccessKeyId": "ASIA...",
"SecretAccessKey": "secret",
"SessionToken": "token"
}
[/code]

You can use this JSON output in other tools or scripts as needed.

### Environment Variables

After successfully assuming an AWS IAM role, the plugin sets the following environment variables with temporary credentials:

- `AWS_ACCESS_KEY_ID`: The temporary access key ID.
- `AWS_SECRET_ACCESS_KEY`: The temporary secret access key.
- `AWS_SESSION_TOKEN`: The session token for temporary credentials.

These environment variables are used by the executed commands (`--composer-command` or `--command`) to authenticate with AWS services using the assumed role's permissions.

### Error Handling

The plugin provides detailed error messages to help troubleshoot common issues:

- **Both Options Specified**: If both `--composer-command` and `--command` are used simultaneously, an error message will be displayed.

  [code=bash]
  [error] You cannot specify both --composer-command and --command options at the same time.
  [/code]

- **Invalid AWS Credentials**: If the provided AWS credentials are invalid or insufficient, appropriate error messages will guide you to resolve the issue.

- **Network Errors**: Issues with network connectivity or AWS SDK configuration will prompt relevant error messages.

- **JSON Encoding Errors**: If the plugin fails to encode credentials as JSON, an error message will notify you.

### License

This project is licensed under the [MIT License](LICENSE).

---

## 日本語

### 概要

**AWS Assume Role Plugin for Composer** は、AWS IAM ロールを MFA（多要素認証）付きでアサインし、一時的なAWS認証情報を使用してComposerコマンドやその他のシェルコマンドを実行するプラグインです。これにより、機密性の高い操作を一時的で限定的な権限の認証情報を使用して実行することで、セキュリティが向上します。

### 特徴

- **MFA付きのAWS IAMロールのアサイン**: MFAが必要なロールを安全にアサインします。
- **Composerコマンドの実行**: 一時的な認証情報を使用してComposerコマンド（`composer install`、`composer update`など）を実行します。
- **一般的なコマンドの実行**: アサインされたロールの認証情報を使用して任意のシェルコマンドを実行します。
- **認証情報のJSON出力**: 一時的なAWS認証情報をJSON形式で取得し、他のツールやスクリプトで利用できます。
- **柔軟な設定**: AWSプロファイルの使用と、ロールおよびMFA設定の手動入力の両方をサポートします。
- **包括的なエラーハンドリング**: トラブルシューティングを支援する詳細なエラーメッセージを提供します。

### インストール

プラグインはComposerを使用してインストールできます。プロジェクトのルートディレクトリで以下のコマンドを実行してください：

[code=bash]
composer require --dev pj8/aws-assume-role-plugin
[/code]

インストール後、Composerがプラグインを認識していることを確認してください。設定に応じて、グローバルまたはプロジェクト内で有効化が必要になる場合があります。

### 設定

#### AWSプロファイル

プラグインは `~/.aws/config` ファイルに定義されたAWSプロファイルを使用します。必要な `role_arn` と `mfa_serial` を持つプロファイルが設定されていることを確認してください。以下は設定例です：

[code=ini]
[profile your-profile]
role_arn = arn:aws:iam::123456789012:role/YourRole
mfa_serial = arn:aws:iam::123456789012:mfa/YourMFADevice
region = us-east-1
[/code]

#### 手動設定

AWSプロファイルを使用しない場合、コマンド実行時に必要な詳細情報を手動で入力することも可能です。

### 使い方

プラグインは以下のオプションを持つ `assume-role` コマンドを提供します：

- `--aws-profile`: 使用するAWS CLIプロファイルを指定します。
- `--composer-command`: アサインされたロールの認証情報を使用してComposerコマンドを実行します。
- `--command`: アサインされたロールの認証情報を使用して一般的なシェルコマンドを実行します。

**注意**: `--composer-command` と `--command` は同時に使用できません。両方が指定された場合、エラーメッセージが表示され、処理が中断します。

両方のオプションが指定されない場合、プラグインは取得したAWS認証情報をJSON形式で標準出力に出力し、処理を終了します。

#### Composer コマンドの実行

特定のAWSプロファイルを使用してロールをアサインし、Composerコマンドを実行するには以下のようにします：

[code=bash]
composer assume-role --aws-profile=your-profile --composer-command=install
[/code]

このコマンドは、指定されたAWS IAMロールをMFA付きでアサインし、一時的な認証情報を使用して `composer install` を実行します。

#### 一般的なコマンドの実行

ロールをアサインし、任意のシェルコマンドを実行するには以下のようにします：

[code=bash]
composer assume-role --aws-profile=your-profile --command="php artisan migrate"
[/code]

このコマンドは、指定されたAWS IAMロールをMFA付きでアサインし、一時的な認証情報を使用して `php artisan migrate` を実行します。

#### 認証情報の JSON 出力

`--composer-command` と `--command` のいずれも指定しない場合、プラグインは一時的なAWS認証情報をJSON形式で標準出力に出力し、処理を終了します：

[code=bash]
composer assume-role --aws-profile=your-profile
[/code]

**出力例**：

[code=json]
{
"AccessKeyId": "ASIA...",
"SecretAccessKey": "secret",
"SessionToken": "token"
}
[/code]

このJSONは、他のツールやスクリプトで利用するために保存や解析が可能です。

### 環境変数

AWS IAM ロールを正常にアサインした後、プラグインは以下の環境変数に一時的な認証情報を設定します：

- `AWS_ACCESS_KEY_ID`: 一時的なアクセスキーID。
- `AWS_SECRET_ACCESS_KEY`: 一時的なシークレットアクセスキー。
- `AWS_SESSION_TOKEN`: 一時的な認証トークン。

これらの環境変数は、実行されるコマンド（`--composer-command` または `--command`）によって、アサインされたロールの権限を使用してAWSサービスに認証するために使用されます。

### エラーハンドリング

プラグインは、一般的な問題を解決するための詳細なエラーメッセージを提供します：

- **両方のオプションが指定された場合**: `--composer-command` と `--command` を同時に使用するとエラーメッセージが表示されます。

  [code=bash]
  [error] You cannot specify both --composer-command and --command options at the same time.
  [/code]

- **無効なAWS認証情報**: 提供されたAWS認証情報が無効または不十分な場合、適切なエラーメッセージが表示されます。

- **ネットワークエラー**: ネットワーク接続やAWS SDKの設定に問題がある場合、関連するエラーメッセージが表示されます。

- **JSONエンコードエラー**: 認証情報をJSON形式でエンコードできない場合、エラーメッセージが表示されます。

### ライセンス

このプロジェクトは [MITライセンス](LICENSE) の下でライセンスされています。
