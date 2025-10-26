FROM php:8.2-apache
WORKDIR /var/www/html

# Install system dependencies - split into stages for better caching
RUN apt-get update && apt-get install -y \
    clamav \
    clamav-daemon \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    tar \
    bzip2 \
    xz-utils \
    postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis \
    && php -m | grep redis \
    && rm -rf /var/lib/apt/lists/*

# Install Java and code signing build tools
RUN apt-get update && apt-get install -y \
    default-jdk \
    openssl \
    ca-certificates \
    curl \
    wget \
    git \
    build-essential \
    cmake \
    pkg-config \
    libssl-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Build and install osslsigncode from source using CMake
RUN cd /tmp && \
    git clone https://github.com/mtrojnar/osslsigncode.git && \
    cd osslsigncode && \
    mkdir build && cd build && \
    cmake -S .. && \
    cmake --build . && \
    cmake --install . && \
    ldconfig && \
    cd / && \
    rm -rf /tmp/osslsigncode

# Copy application files
COPY app/public/ /var/www/html/
COPY app/src/ /var/www/src/
COPY app/config/.env.example /var/www/.env

# Copy database migrations and scripts
COPY db/migrations/ /var/www/db/migrations/
COPY scripts/run-migrations.sh /var/www/scripts/run-migrations.sh
RUN chmod +x /var/www/scripts/run-migrations.sh

# Copy whitelist configuration
COPY config/whitelist.json /tmp/access_veribits.json

# Create logs directory and fix permissions for web server
RUN mkdir -p /var/www/logs && \
    chown -R www-data:www-data /var/www/html /var/www/logs && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/logs

# Configure Apache
RUN a2enmod rewrite headers && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf && \
    echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Create directories for file uploads
RUN mkdir -p /tmp/veribits-scans /tmp/veribits-archives \
    && chown -R www-data:www-data /tmp/veribits-scans /tmp/veribits-archives

# Configure ClamAV
RUN mkdir -p /var/run/clamav && chown -R clamav:clamav /var/run/clamav

# Create directories for code signing
RUN mkdir -p /etc/veribits/certs /tmp/veribits-signing \
    && chown -R www-data:www-data /etc/veribits/certs /tmp/veribits-signing

# Generate proper certificate hierarchy for code signing
# Step 1: Create Root CA (After Dark Systems Object Signing CA)
RUN openssl genrsa -out /etc/veribits/certs/ca-key.pem 4096 && \
    openssl req -new -x509 -days 7300 -key /etc/veribits/certs/ca-key.pem \
    -out /etc/veribits/certs/ca-cert.pem \
    -subj "/C=US/ST=Delaware/L=Wilmington/O=After Dark Systems LLC/CN=After Dark Systems Object Signing CA" \
    -extensions v3_ca && \
    # Step 2: Create Code Signing Certificate signed by CA
    openssl genrsa -out /etc/veribits/certs/codesign-key.pem 2048 && \
    openssl req -new -key /etc/veribits/certs/codesign-key.pem \
    -out /etc/veribits/certs/codesign.csr \
    -subj "/C=US/ST=Delaware/L=Wilmington/O=After Dark Systems LLC/OU=VeriBits/CN=After Dark Systems Object Signing Certificate" && \
    # Create extensions file for code signing
    echo "[ codesign_ext ]" > /tmp/codesign_ext.cnf && \
    echo "basicConstraints=CA:FALSE" >> /tmp/codesign_ext.cnf && \
    echo "keyUsage=digitalSignature" >> /tmp/codesign_ext.cnf && \
    echo "extendedKeyUsage=codeSigning,timeStamping" >> /tmp/codesign_ext.cnf && \
    echo "subjectKeyIdentifier=hash" >> /tmp/codesign_ext.cnf && \
    echo "authorityKeyIdentifier=keyid,issuer" >> /tmp/codesign_ext.cnf && \
    # Sign the certificate with CA
    openssl x509 -req -in /etc/veribits/certs/codesign.csr \
    -CA /etc/veribits/certs/ca-cert.pem -CAkey /etc/veribits/certs/ca-key.pem \
    -CAcreateserial -out /etc/veribits/certs/codesign-cert.pem -days 3650 \
    -extfile /tmp/codesign_ext.cnf -extensions codesign_ext && \
    # Create certificate chain (cert + CA)
    cat /etc/veribits/certs/codesign-cert.pem /etc/veribits/certs/ca-cert.pem > /etc/veribits/certs/codesign-chain.pem && \
    # Create PKCS12 bundle with chain
    openssl pkcs12 -export -out /etc/veribits/certs/codesign.pfx \
    -inkey /etc/veribits/certs/codesign-key.pem \
    -in /etc/veribits/certs/codesign-chain.pem \
    -certfile /etc/veribits/certs/ca-cert.pem \
    -name "After Dark Systems Object Signing Certificate" \
    -passout pass:veribits2024 && \
    # Generate Java keystore for JAR signing with proper cert chain
    keytool -genkeypair -alias veribits -keyalg RSA -keysize 2048 \
    -validity 3650 -keystore /etc/veribits/certs/veribits.jks \
    -storepass veribits2024 -keypass veribits2024 \
    -dname "CN=After Dark Systems Object Signing Certificate, OU=VeriBits, O=After Dark Systems LLC, L=Wilmington, ST=Delaware, C=US" && \
    # Set permissions
    chown -R www-data:www-data /etc/veribits/certs && \
    chmod 644 /etc/veribits/certs/* && \
    chmod 600 /etc/veribits/certs/*-key.pem && \
    # Cleanup
    rm -f /tmp/codesign_ext.cnf /etc/veribits/certs/*.csr

EXPOSE 80
