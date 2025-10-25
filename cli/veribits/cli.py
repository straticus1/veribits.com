#!/usr/bin/env python3
"""
VeriBits CLI - Command-line interface for all VeriBits tools
"""

import click
import requests
import json
import sys
from rich.console import Console
from rich.table import Table
from rich.syntax import Syntax
from rich.panel import Panel
from pathlib import Path
import os

console = Console()

# Default API endpoint
DEFAULT_API_URL = "https://veribits.com/api/v1"
API_URL = os.getenv("VERIBITS_API_URL", DEFAULT_API_URL)
API_KEY = os.getenv("VERIBITS_API_KEY", "")


def api_request(endpoint, method="GET", data=None, files=None):
    """Make API request to VeriBits"""
    url = f"{API_URL}{endpoint}"
    headers = {}

    if API_KEY:
        headers["Authorization"] = f"Bearer {API_KEY}"

    try:
        if method == "GET":
            response = requests.get(url, headers=headers)
        elif method == "POST":
            if files:
                response = requests.post(url, headers=headers, files=files)
            else:
                headers["Content-Type"] = "application/json"
                response = requests.post(url, headers=headers, json=data)

        response.raise_for_status()
        return response.json()

    except requests.exceptions.RequestException as e:
        console.print(f"[bold red]Error:[/] {str(e)}")
        if hasattr(e, 'response') and e.response is not None:
            try:
                error_data = e.response.json()
                console.print(f"[red]{error_data.get('error', {}).get('message', 'Unknown error')}[/]")
            except:
                pass
        sys.exit(1)


@click.group()
@click.version_option(version="1.0.0")
def main():
    """VeriBits CLI - Professional security and developer tools"""
    pass


@main.command()
@click.argument("token")
@click.option("--secret", "-s", help="Secret key for signature verification")
@click.option("--verify", is_flag=True, help="Verify token signature")
def jwt_decode(token, secret, verify):
    """Decode and verify JWT token"""
    console.print("[bold cyan]Decoding JWT Token...[/]\n")

    result = api_request("/jwt/decode", "POST", {
        "token": token,
        "secret": secret or "",
        "verify_signature": verify
    })

    data = result.get("data", {})

    # Display header
    console.print(Panel("[bold]Header", style="cyan"))
    console.print(Syntax(json.dumps(data.get("header", {}), indent=2), "json", theme="monokai"))

    # Display payload
    console.print("\n" + Panel("[bold]Payload", style="cyan"))
    console.print(Syntax(json.dumps(data.get("payload", {}), indent=2), "json", theme="monokai"))

    # Display claims
    if data.get("claims"):
        console.print("\n[bold cyan]Claims:[/]")
        for key, value in data["claims"].items():
            if key in ["expired", "not_yet_valid"]:
                color = "red" if value else "green"
                icon = "❌" if value else "✅"
                console.print(f"  {key}: [{color}]{value} {icon}[/]")
            else:
                console.print(f"  {key}: {value}")

    # Signature verification
    if verify:
        verified = data.get("signature_verified", False)
        color = "green" if verified else "red"
        icon = "✅" if verified else "❌"
        console.print(f"\n[bold]Signature Verified:[/] [{color}]{verified} {icon}[/]")


@main.command()
@click.option("--secret", "-s", required=True, help="Secret key for signing")
@click.option("--payload", "-p", required=True, help="JSON payload")
@click.option("--expires", "-e", default=3600, help="Expiration time in seconds")
def jwt_sign(secret, payload, expires):
    """Generate new JWT token"""
    console.print("[bold cyan]Generating JWT Token...[/]\n")

    try:
        payload_data = json.loads(payload)
    except json.JSONDecodeError:
        console.print("[bold red]Error:[/] Invalid JSON payload")
        sys.exit(1)

    result = api_request("/jwt/sign", "POST", {
        "secret": secret,
        "payload": payload_data,
        "expires_in": expires
    })

    data = result.get("data", {})

    console.print(Panel(data.get("token", ""), title="Generated JWT Token", style="green"))
    console.print(f"\n[bold]Algorithm:[/] {data.get('algorithm')}")
    console.print(f"[bold]Expires In:[/] {data.get('expires_in')} seconds")
    if data.get("expires_at"):
        console.print(f"[bold]Expires At:[/] {data.get('expires_at')}")


@main.command()
@click.argument("pattern")
@click.argument("text")
@click.option("--flags", "-f", default="g", help="Regex flags (g, i, m)")
def regex(pattern, text, flags):
    """Test regular expression"""
    console.print("[bold cyan]Testing Regex Pattern...[/]\n")

    result = api_request("/tools/regex-test", "POST", {
        "pattern": pattern,
        "text": text,
        "flags": flags
    })

    data = result.get("data", {})

    console.print(f"[bold]Pattern:[/] [cyan]{data.get('pattern')}[/]")
    console.print(f"[bold]Matches Found:[/] {data.get('match_count')}\n")

    matches = data.get("matches", [])
    if matches:
        table = Table(title="Matches")
        table.add_column("#", style="cyan")
        table.add_column("Match", style="yellow")
        table.add_column("Position", style="green")

        for i, match in enumerate(matches, 1):
            table.add_row(str(i), match["match"], str(match["position"]))

        console.print(table)
    else:
        console.print("[yellow]No matches found[/]")


@main.command()
@click.argument("file_path", type=click.Path(exists=True))
def secrets(file_path):
    """Scan file for exposed secrets"""
    console.print("[bold cyan]Scanning for Secrets...[/]\n")

    with open(file_path, 'r') as f:
        text = f.read()

    result = api_request("/tools/scan-secrets", "POST", {
        "text": text
    })

    data = result.get("data", {})

    secrets_found = data.get("secrets_found", 0)
    risk_level = data.get("risk_level", "low")

    # Risk level styling
    risk_colors = {"low": "green", "medium": "yellow", "high": "red"}
    risk_color = risk_colors.get(risk_level, "white")

    console.print(f"[bold]Secrets Found:[/] [{risk_color}]{secrets_found}[/]")
    console.print(f"[bold]Risk Level:[/] [{risk_color}]{risk_level.upper()}[/]\n")

    secrets = data.get("secrets", [])
    if secrets:
        table = Table(title="Detected Secrets")
        table.add_column("Type", style="cyan")
        table.add_column("Value", style="yellow")
        table.add_column("Line", style="green")
        table.add_column("Severity", style="red")

        for secret in secrets:
            severity_colors = {"critical": "red", "high": "yellow", "medium": "blue"}
            severity = secret["severity"]
            severity_style = severity_colors.get(severity, "white")

            table.add_row(
                secret["type"],
                secret["value"],
                str(secret["line"]),
                f"[{severity_style}]{severity.upper()}[/]"
            )

        console.print(table)
    else:
        console.print("[bold green]✅ No secrets detected![/]")


@main.command()
@click.argument("text")
@click.option("--algorithms", "-a", multiple=True, default=["md5", "sha256", "sha512"],
              help="Hash algorithms to use")
def hash(text, algorithms):
    """Generate hashes for text"""
    console.print("[bold cyan]Generating Hashes...[/]\n")

    result = api_request("/tools/generate-hash", "POST", {
        "text": text,
        "algorithms": list(algorithms)
    })

    data = result.get("data", {})
    hashes = data.get("hashes", {})

    table = Table(title="Generated Hashes")
    table.add_column("Algorithm", style="cyan")
    table.add_column("Hash", style="yellow")

    for algo, hash_value in hashes.items():
        table.add_row(algo.upper(), hash_value)

    console.print(table)


@main.command()
@click.argument("address")
@click.option("--type", "-t", type=click.Choice(["address", "transaction"]), default="address",
              help="Validation type")
def bitcoin(address, type):
    """Validate Bitcoin address or transaction"""
    console.print("[bold cyan]Validating Bitcoin...[/]\n")

    result = api_request("/crypto/validate/bitcoin", "POST", {
        "value": address,
        "type": type
    })

    data = result.get("data", {})

    is_valid = data.get("is_valid", False)
    color = "green" if is_valid else "red"
    icon = "✅" if is_valid else "❌"

    console.print(f"[bold]Status:[/] [{color}]{icon} {'Valid' if is_valid else 'Invalid'}[/]\n")

    if data.get("format"):
        console.print(f"[bold]Format:[/] {data['format']}")
    if data.get("network"):
        console.print(f"[bold]Network:[/] {data['network']}")

    details = data.get("details", {})
    if details:
        console.print("\n[bold]Details:[/]")
        for key, value in details.items():
            console.print(f"  {key}: {value}")


@main.command()
@click.argument("address")
@click.option("--type", "-t", type=click.Choice(["address", "transaction"]), default="address",
              help="Validation type")
def ethereum(address, type):
    """Validate Ethereum address or transaction"""
    console.print("[bold cyan]Validating Ethereum...[/]\n")

    result = api_request("/crypto/validate/ethereum", "POST", {
        "value": address,
        "type": type
    })

    data = result.get("data", {})

    is_valid = data.get("is_valid", False)
    checksum_valid = data.get("checksum_valid", False)

    color = "green" if is_valid else "red"
    icon = "✅" if is_valid else "❌"

    console.print(f"[bold]Status:[/] [{color}]{icon} {'Valid' if is_valid else 'Invalid'}[/]")

    if type == "address":
        checksum_color = "green" if checksum_valid else "yellow"
        checksum_icon = "✅" if checksum_valid else "⚠️"
        console.print(f"[bold]Checksum:[/] [{checksum_color}]{checksum_icon} {'Valid' if checksum_valid else 'Invalid/Missing'}[/]")

        if data.get("details", {}).get("checksum_address"):
            console.print(f"\n[bold]Checksum Address:[/]")
            console.print(f"[cyan]{data['details']['checksum_address']}[/]")


@main.command()
@click.argument("file_path", type=click.Path(exists=True))
def file_magic(file_path):
    """Detect file type by magic number"""
    console.print("[bold cyan]Analyzing File Magic Number...[/]\n")

    with open(file_path, 'rb') as f:
        files = {'file': f}
        result = api_request("/file-magic", "POST", files=files)

    data = result.get("data", {})

    console.print(f"[bold]Detected Type:[/] [green]{data.get('detected_type')}[/]")
    console.print(f"[bold]Extension:[/] {data.get('detected_extension')}")
    console.print(f"[bold]MIME Type:[/] {data.get('detected_mime')}")
    console.print(f"[bold]File Hash:[/] [cyan]{data.get('file_hash')}[/]")


@main.command()
def config():
    """Show current configuration"""
    console.print("[bold cyan]VeriBits CLI Configuration[/]\n")

    console.print(f"[bold]API URL:[/] {API_URL}")
    console.print(f"[bold]API Key:[/] {'Set ✅' if API_KEY else 'Not set ❌'}")

    console.print("\n[bold]Environment Variables:[/]")
    console.print("  VERIBITS_API_URL - Override API endpoint")
    console.print("  VERIBITS_API_KEY - Set API key for authenticated requests")


@main.command()
def limits():
    """Check anonymous usage limits"""
    result = api_request("/limits/anonymous", "GET")
    data = result.get("data", {})

    console.print("[bold cyan]Anonymous Usage Limits[/]\n")
    console.print(f"[bold]Free Scans:[/] {data.get('free_scans', 5)}")
    console.print(f"[bold]Scans Remaining:[/] [green]{data.get('scans_remaining', 5)}[/]")
    console.print(f"[bold]Max File Size:[/] {data.get('max_file_size_mb', 50)} MB")
    console.print(f"[bold]Trial Window:[/] {data.get('trial_window_days', 30)} days")


@main.command()
@click.argument("domain")
@click.option("--type", "-t", default="A",
              type=click.Choice(["A", "AAAA", "MX", "TXT", "CNAME", "NS", "SOA", "PTR", "SRV", "CAA"]),
              help="DNS record type")
def dns(domain, type):
    """Validate DNS records for a domain"""
    console.print(f"[bold cyan]Validating DNS Records for {domain}...[/]\n")

    result = api_request("/tools/dns-validate", "POST", {
        "domain": domain,
        "record_type": type
    })

    data = result.get("data", {})
    records = data.get("records", [])

    if records:
        table = Table(title=f"DNS Records ({type})")
        table.add_column("Type", style="cyan")
        table.add_column("Value", style="yellow")
        table.add_column("TTL", style="green")
        table.add_column("Priority", style="blue")

        for record in records:
            table.add_row(
                record.get("type", ""),
                record.get("value", ""),
                str(record.get("ttl", "")),
                str(record.get("priority", "")) if record.get("priority") else ""
            )

        console.print(table)
    else:
        console.print("[yellow]No DNS records found[/]")

    dnssec = data.get("dnssec", {})
    if dnssec:
        enabled = dnssec.get("enabled", False)
        icon = "✅" if enabled else "❌"
        color = "green" if enabled else "yellow"
        console.print(f"\n[bold]DNSSEC:[/] [{color}]{icon} {'Enabled' if enabled else 'Not Enabled'}[/]")


@main.command()
@click.argument("query")
def whois(query):
    """WHOIS lookup for domain or IP address"""
    console.print(f"[bold cyan]WHOIS Lookup for {query}...[/]\n")

    result = api_request("/tools/whois", "POST", {
        "query": query
    })

    data = result.get("data", {})

    console.print(f"[bold]Query:[/] {data.get('query')}")
    console.print(f"[bold]Query Type:[/] {data.get('query_type').upper()}")
    console.print(f"[bold]WHOIS Server:[/] {data.get('whois_server')}\n")

    # Display parsed data
    parsed = data.get("parsed", {})
    if parsed:
        console.print(Panel("[bold]Parsed Information", style="cyan"))
        for key, value in parsed.items():
            console.print(f"  [bold]{key}:[/] {value}")

    # Display raw response
    raw = data.get("raw_response", "")
    if raw:
        console.print("\n" + Panel("[bold]Raw WHOIS Response", style="cyan"))
        # Limit raw output to first 50 lines for readability
        raw_lines = raw.split("\n")[:50]
        console.print("\n".join(raw_lines))
        if len(raw.split("\n")) > 50:
            console.print("[dim]... (truncated, see full response for details)[/]")


@main.command()
@click.argument("ip")
@click.option("--subnet", "-s", help="Subnet mask (e.g., 255.255.255.0 or CIDR /24)")
def ipcalc(ip, subnet):
    """Calculate IP subnet information"""
    console.print(f"[bold cyan]Calculating IP Subnet Information...[/]\n")

    data_payload = {"ip": ip}
    if subnet:
        data_payload["subnet_mask"] = subnet

    result = api_request("/tools/ip-calculate", "POST", data_payload)
    data = result.get("data", {})

    console.print(f"[bold]IP Address:[/] {data.get('ip_address')}")
    console.print(f"[bold]CIDR Notation:[/] [cyan]{data.get('cidr')}[/]")
    console.print(f"[bold]Network Address:[/] {data.get('network_address')}")
    console.print(f"[bold]Broadcast Address:[/] {data.get('broadcast_address')}")
    console.print(f"[bold]Subnet Mask:[/] {data.get('subnet_mask')}")
    console.print(f"[bold]Wildcard Mask:[/] {data.get('wildcard_mask')}")
    console.print(f"[bold]First Usable IP:[/] [green]{data.get('first_usable')}[/]")
    console.print(f"[bold]Last Usable IP:[/] [green]{data.get('last_usable')}[/]")
    console.print(f"[bold]Total Hosts:[/] {data.get('total_hosts')}")
    console.print(f"[bold]Usable Hosts:[/] {data.get('usable_hosts')}")
    console.print(f"[bold]IP Class:[/] {data.get('ip_class')}")
    console.print(f"[bold]IP Type:[/] {data.get('ip_type')}")


@main.command()
@click.argument("ip")
def rbl(ip):
    """Check if IP is listed on RBL/DNSBL blacklists"""
    console.print(f"[bold cyan]Checking RBL Blacklists for {ip}...[/]\n")

    result = api_request("/tools/rbl-check", "POST", {
        "ip": ip
    })

    data = result.get("data", {})

    listed = data.get("listed", False)
    icon = "❌" if listed else "✅"
    color = "red" if listed else "green"

    console.print(f"[bold]Status:[/] [{color}]{icon} {'Listed on blacklists' if listed else 'Not listed on blacklists'}[/]")
    console.print(f"[bold]Blacklists Checked:[/] {data.get('blacklists_checked', 0)}")
    console.print(f"[bold]Blacklists Found:[/] [{color}]{data.get('blacklists_found', 0)}[/]\n")

    listings = data.get("listings", [])
    if listings:
        table = Table(title="Blacklist Listings")
        table.add_column("RBL", style="cyan")
        table.add_column("Reason", style="yellow")

        for listing in listings:
            table.add_row(listing.get("rbl", ""), listing.get("reason", ""))

        console.print(table)
    else:
        console.print("[bold green]✅ IP is clean - not listed on any checked RBLs[/]")


@main.command()
@click.argument("target")
def smtp_relay(target):
    """Check SMTP relay status for domain or email"""
    console.print(f"[bold cyan]Checking SMTP Relay for {target}...[/]\n")

    result = api_request("/tools/smtp-relay-check", "POST", {
        "target": target
    })

    data = result.get("data", {})

    is_relay = data.get("is_open_relay", False)
    icon = "❌" if is_relay else "✅"
    color = "red" if is_relay else "green"

    console.print(f"[bold]SMTP Server:[/] {data.get('server')}")
    console.print(f"[bold]Open Relay:[/] [{color}]{icon} {'YES - VULNERABLE' if is_relay else 'NO - SECURE'}[/]\n")

    tests = data.get("tests_performed", [])
    if tests:
        table = Table(title="SMTP Tests")
        table.add_column("Test", style="cyan")
        table.add_column("Result", style="yellow")
        table.add_column("Details", style="blue")

        for test in tests:
            passed = test.get("passed", False)
            result_icon = "✅" if passed else "❌"
            table.add_row(
                test.get("test", ""),
                f"{result_icon} {test.get('result', '')}",
                test.get("details", "")
            )

        console.print(table)

    if is_relay:
        console.print("\n[bold red]⚠️  WARNING: Open relay detected![/]")
        console.print("This server can be abused for spam. Please secure your SMTP server.")


@main.command()
@click.argument("cert_file", type=click.Path(exists=True))
@click.argument("key_file", type=click.Path(exists=True))
@click.option("--format", "-f", type=click.Choice(["pkcs12", "jks"]), default="pkcs12",
              help="Output format (pkcs12 or jks)")
@click.option("--password", "-p", default="", help="Password for keystore")
@click.option("--alias", "-a", default="mycert", help="Certificate alias")
@click.option("--output", "-o", type=click.Path(), help="Output file path")
def cert_convert(cert_file, key_file, format, password, alias, output):
    """Convert PEM certificate to PKCS12 or JKS format

    This command converts PEM-formatted certificates and keys to PKCS12 or JKS keystores.
    Processing is done locally on your machine for security.

    Examples:

        veribits cert-convert cert.pem key.pem --format pkcs12 -o cert.p12

        veribits cert-convert cert.pem key.pem --format jks --password changeit -o cert.jks
    """
    import subprocess
    import tempfile

    console.print("[bold cyan]Converting Certificate...[/]\n")

    # Determine output filename
    if not output:
        output = f"certificate.{format if format == 'jks' else 'p12'}"

    try:
        if format == "pkcs12":
            # Convert directly to PKCS12 using openssl
            cmd = [
                "openssl", "pkcs12", "-export",
                "-in", cert_file,
                "-inkey", key_file,
                "-out", output,
                "-name", alias,
                "-passout", f"pass:{password}"
            ]

            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode != 0:
                console.print(f"[bold red]Error:[/] {result.stderr}")
                sys.exit(1)

            console.print(f"[bold green]✅ Certificate converted successfully![/]")
            console.print(f"[bold]Format:[/] PKCS12")
            console.print(f"[bold]Output File:[/] {output}")
            console.print(f"[bold]Alias:[/] {alias}")

        elif format == "jks":
            # Convert via PKCS12 intermediate
            with tempfile.NamedTemporaryFile(suffix='.p12', delete=False) as tmp:
                p12_file = tmp.name

            # First create PKCS12
            cmd = [
                "openssl", "pkcs12", "-export",
                "-in", cert_file,
                "-inkey", key_file,
                "-out", p12_file,
                "-name", alias,
                "-passout", f"pass:{password or 'changeit'}"
            ]

            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode != 0:
                console.print(f"[bold red]Error creating PKCS12:[/] {result.stderr}")
                os.unlink(p12_file)
                sys.exit(1)

            # Then convert PKCS12 to JKS using keytool
            cmd = [
                "keytool", "-importkeystore",
                "-srckeystore", p12_file,
                "-srcstoretype", "PKCS12",
                "-srcstorepass", password or 'changeit',
                "-destkeystore", output,
                "-deststoretype", "JKS",
                "-deststorepass", password or 'changeit'
            ]

            result = subprocess.run(cmd, capture_output=True, text=True)

            # Clean up temp file
            os.unlink(p12_file)

            if result.returncode != 0:
                console.print(f"[bold red]Error creating JKS:[/] {result.stderr}")
                console.print("\n[yellow]Note: keytool (Java) must be installed for JKS conversion[/]")
                sys.exit(1)

            console.print(f"[bold green]✅ Certificate converted successfully![/]")
            console.print(f"[bold]Format:[/] JKS (Java KeyStore)")
            console.print(f"[bold]Output File:[/] {output}")
            console.print(f"[bold]Alias:[/] {alias}")

        # Security reminder
        console.print("\n[bold yellow]⚠️  Security Note:[/]")
        console.print("Store the keystore file securely and use a strong password.")
        console.print("This conversion was performed locally on your machine.")

    except FileNotFoundError as e:
        console.print(f"[bold red]Error:[/] Required tool not found")
        if "openssl" in str(e):
            console.print("Please install OpenSSL: https://www.openssl.org/")
        elif "keytool" in str(e):
            console.print("Please install Java JDK for keytool: https://www.oracle.com/java/")
        sys.exit(1)
    except Exception as e:
        console.print(f"[bold red]Error:[/] {str(e)}")
        sys.exit(1)


@main.command()
@click.argument("target")
@click.option("--max-hops", "-m", default=30, help="Maximum number of hops")
def traceroute(target, max_hops):
    """Perform visual traceroute to destination"""
    console.print(f"[bold cyan]Tracing route to {target}...[/]\n")
    console.print("[dim]This may take 30-60 seconds...[/]\n")

    result = api_request("/tools/traceroute", "POST", {
        "target": target,
        "max_hops": max_hops
    })

    data = result.get("data", {})
    hops = data.get("hops", [])

    console.print(f"[bold]Target:[/] {data.get('target')}")
    console.print(f"[bold]Total Hops:[/] {data.get('total_hops')}\n")

    if hops:
        table = Table(title="Traceroute Hops")
        table.add_column("Hop", style="cyan", justify="right")
        table.add_column("IP Address", style="yellow")
        table.add_column("Hostname", style="green")
        table.add_column("Location", style="blue")
        table.add_column("Latency (ms)", style="magenta")

        for hop in hops:
            if hop.get("timeout"):
                table.add_row(
                    str(hop["hop"]),
                    "*",
                    "Request timed out",
                    "-",
                    "-"
                )
            else:
                avg_latency = "N/A"
                if hop.get("latencies"):
                    avg = sum(hop["latencies"]) / len(hop["latencies"])
                    avg_latency = f"{avg:.2f}"

                location = "-"
                if hop.get("location"):
                    loc = hop["location"]
                    location = f"{loc.get('city', '')}, {loc.get('country', '')}".strip(", ")

                table.add_row(
                    str(hop["hop"]),
                    hop.get("ip", "N/A"),
                    hop.get("hostname", "-"),
                    location,
                    avg_latency
                )

        console.print(table)
    else:
        console.print("[yellow]No hops found[/]")


@main.command()
@click.argument("query")
def bgp_prefix(query):
    """Lookup BGP prefix or IP address"""
    console.print(f"[bold cyan]BGP Prefix Lookup for {query}...[/]\n")

    result = api_request("/bgp/prefix", "POST", {
        "query": query
    })

    data = result.get("data", {})

    console.print(f"[bold]Prefix:[/] [cyan]{data.get('prefix')}[/]")
    console.print(f"[bold]Name:[/] {data.get('name', 'N/A')}")
    console.print(f"[bold]Description:[/] {data.get('description', 'N/A')}")
    console.print(f"[bold]Country:[/] {data.get('country_code', 'N/A')}")
    console.print(f"[bold]RIR:[/] {data.get('rir_name', 'N/A')}")

    asns = data.get("asns", [])
    if asns:
        asn_list = ", ".join([f"AS{asn.get('asn')}" for asn in asns])
        console.print(f"[bold]Origin ASNs:[/] {asn_list}")

    rpki = data.get("rpki_validation", 'unknown')
    rpki_colors = {"valid": "green", "invalid": "red", "unknown": "yellow"}
    rpki_color = rpki_colors.get(rpki, "white")
    console.print(f"[bold]RPKI Status:[/] [{rpki_color}]{rpki.upper()}[/]")


@main.command()
@click.argument("asn")
def bgp_asn(asn):
    """Lookup AS (Autonomous System) information"""
    console.print(f"[bold cyan]BGP AS Lookup for {asn}...[/]\n")

    result = api_request("/bgp/asn", "POST", {
        "asn": asn
    })

    data = result.get("data", {})

    console.print(f"[bold]ASN:[/] AS{data.get('asn')}")
    console.print(f"[bold]Name:[/] {data.get('name', 'N/A')}")
    console.print(f"[bold]Description:[/] {data.get('description', 'N/A')}")
    console.print(f"[bold]Country:[/] {data.get('country_code', 'N/A')}")

    if data.get('website'):
        console.print(f"[bold]Website:[/] [link={data['website']}]{data['website']}[/]")

    if data.get('looking_glass'):
        console.print(f"[bold]Looking Glass:[/] {data['looking_glass']}")

    if data.get('traffic_estimation'):
        console.print(f"[bold]Traffic Estimation:[/] {data['traffic_estimation']}")

    if data.get('traffic_ratio'):
        console.print(f"[bold]Traffic Ratio:[/] {data['traffic_ratio']}")

    contacts = data.get("email_contacts", [])
    if contacts:
        console.print(f"\n[bold]Email Contacts:[/]")
        for contact in contacts:
            console.print(f"  • {contact}")


@main.command()
@click.argument("asn")
def bgp_prefixes(asn):
    """Get prefixes announced by an AS"""
    console.print(f"[bold cyan]Getting Prefixes for AS{asn}...[/]\n")

    result = api_request("/bgp/asn/prefixes", "POST", {
        "asn": asn
    })

    data = result.get("data", {})

    console.print(f"[bold]IPv4 Prefixes:[/] {data.get('ipv4_count', 0)}")
    console.print(f"[bold]IPv6 Prefixes:[/] {data.get('ipv6_count', 0)}\n")

    ipv4_prefixes = data.get("ipv4_prefixes", [])
    if ipv4_prefixes:
        table = Table(title="IPv4 Prefixes (First 50)")
        table.add_column("Prefix", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Description", style="green")

        for prefix in ipv4_prefixes[:50]:
            table.add_row(
                prefix.get("prefix", ""),
                prefix.get("name", ""),
                prefix.get("description", "")
            )

        console.print(table)

        if len(ipv4_prefixes) > 50:
            console.print(f"\n[dim]... and {len(ipv4_prefixes) - 50} more prefixes[/]")


@main.command()
@click.argument("asn")
def bgp_peers(asn):
    """Get BGP peers for an AS"""
    console.print(f"[bold cyan]Getting BGP Peers for AS{asn}...[/]\n")

    result = api_request("/bgp/asn/peers", "POST", {
        "asn": asn
    })

    data = result.get("data", {})

    console.print(f"[bold]IPv4 Peers:[/] {data.get('ipv4_peer_count', 0)}")
    console.print(f"[bold]IPv6 Peers:[/] {data.get('ipv6_peer_count', 0)}\n")

    ipv4_peers = data.get("ipv4_peers", [])
    if ipv4_peers:
        table = Table(title="IPv4 Peers")
        table.add_column("ASN", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Country", style="green")

        for peer in ipv4_peers:
            table.add_row(
                f"AS{peer.get('asn', '')}",
                peer.get("name", ""),
                peer.get("country_code", "")
            )

        console.print(table)


@main.command()
@click.argument("asn")
def bgp_upstreams(asn):
    """Get transit providers (upstreams) for an AS"""
    console.print(f"[bold cyan]Getting Transit Providers for AS{asn}...[/]\n")

    result = api_request("/bgp/asn/upstreams", "POST", {
        "asn": asn
    })

    data = result.get("data", {})

    console.print(f"[bold]IPv4 Upstreams:[/] {data.get('ipv4_upstream_count', 0)}")
    console.print(f"[bold]IPv6 Upstreams:[/] {data.get('ipv6_upstream_count', 0)}\n")

    ipv4_upstreams = data.get("ipv4_upstreams", [])
    if ipv4_upstreams:
        table = Table(title="IPv4 Transit Providers")
        table.add_column("ASN", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Country", style="green")

        for upstream in ipv4_upstreams:
            table.add_row(
                f"AS{upstream.get('asn', '')}",
                upstream.get("name", ""),
                upstream.get("country_code", "")
            )

        console.print(table)
    else:
        console.print("[yellow]No upstream providers found - this may be a Tier-1 AS[/]")


@main.command()
@click.argument("asn")
def bgp_downstreams(asn):
    """Get customers (downstreams) for an AS"""
    console.print(f"[bold cyan]Getting Customers for AS{asn}...[/]\n")

    result = api_request("/bgp/asn/downstreams", "POST", {
        "asn": asn
    })

    data = result.get("data", {})

    console.print(f"[bold]IPv4 Downstreams:[/] {data.get('ipv4_downstream_count', 0)}")
    console.print(f"[bold]IPv6 Downstreams:[/] {data.get('ipv6_downstream_count', 0)}\n")

    ipv4_downstreams = data.get("ipv4_downstreams", [])
    if ipv4_downstreams:
        table = Table(title="IPv4 Customers (First 50)")
        table.add_column("ASN", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Country", style="green")

        for downstream in ipv4_downstreams[:50]:
            table.add_row(
                f"AS{downstream.get('asn', '')}",
                downstream.get("name", ""),
                downstream.get("country_code", "")
            )

        console.print(table)

        if len(ipv4_downstreams) > 50:
            console.print(f"\n[dim]... and {len(ipv4_downstreams) - 50} more customers[/]")
    else:
        console.print("[yellow]No downstream customers found[/]")


@main.command()
@click.argument("query")
def bgp_search(query):
    """Search for AS by name or description"""
    console.print(f"[bold cyan]Searching BGP for '{query}'...[/]\n")

    result = api_request("/bgp/search", "POST", {
        "query": query
    })

    data = result.get("data", {})
    results = data.get("results", {})

    asns = results.get("asns", [])
    ipv4_prefixes = results.get("ipv4_prefixes", [])
    ipv6_prefixes = results.get("ipv6_prefixes", [])

    total_results = len(asns) + len(ipv4_prefixes) + len(ipv6_prefixes)

    if total_results == 0:
        console.print("[yellow]No results found[/]")
        return

    console.print(f"[bold]Total Results:[/] {total_results}\n")

    if asns:
        table = Table(title="Autonomous Systems")
        table.add_column("ASN", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Country", style="green")

        for asn in asns:
            table.add_row(
                f"AS{asn.get('asn', '')}",
                asn.get("name", ""),
                asn.get("country_code", "")
            )

        console.print(table)

    if ipv4_prefixes:
        console.print("\n")
        table = Table(title="IPv4 Prefixes")
        table.add_column("Prefix", style="cyan")
        table.add_column("Name", style="yellow")
        table.add_column("Description", style="green")

        for prefix in ipv4_prefixes:
            table.add_row(
                prefix.get("prefix", ""),
                prefix.get("name", ""),
                prefix.get("description", "")
            )

        console.print(table)


if __name__ == "__main__":
    main()
