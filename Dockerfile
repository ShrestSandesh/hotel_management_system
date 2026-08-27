FROM php:8.1-apache

RUN docker-php-ext-install mysqli

# Copy the Azure CA certificate
COPY DigiCertGlobalRootCA.crt.pem /usr/local/share/ca-certificates/DigiCertGlobalRootCA.crt.pem

# Register the certificate
RUN update-ca-certificates

# Copy website files
COPY ./html/ /var/www/html/

EXPOSE 80