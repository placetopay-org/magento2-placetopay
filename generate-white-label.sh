#!/bin/bash

# Generar versiones de marca blanca del módulo Magento 2 PlacetoPay
# Este script crea versiones personalizadas para diferentes clientes

set -e

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sin Color

# Directorio base (el script está en la raíz del proyecto)
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMP_DIR="${BASE_DIR}/temp_builds"
OUTPUT_DIR="${BASE_DIR}/builds"
CONFIG_FILE="${BASE_DIR}/config/clients.php"

# Funciones para imprimir con colores
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Función para obtener el nombre de la clase de configuración desde la clave del cliente
get_config_class_name() {
    local client_key="$1"
    
    case "$client_key" in
        "placetopay_colombia") echo "PlacetopayColombiaConfig" ;;
        "placetopay_ecuador") echo "PlacetopayEcuadorConfig" ;;
        "placetopay_belice") echo "PlacetopayBeliceConfig" ;;
        "placetopay_honduras") echo "PlacetopayHondurasConfig" ;;
        "placetopay_uruguay") echo "PlacetopayUruguayConfig" ;;
        "getnet_chile") echo "GetnetChileConfig" ;;
        "banchile_chile") echo "BanchileChileConfig" ;;
        "avalpay_colombia") echo "AvalpayColombiaConfig" ;;
        *)
            echo "$client_key" | awk -F'_' '{
                result = ""
                for (i=1; i<=NF; i++) {
                    word = $i
                    if (length(word) > 0) {
                        first = toupper(substr(word,1,1))
                        rest = tolower(substr(word,2))
                        result = result first rest
                    }
                }
                print result "Config"
            }'
            ;;
    esac
}

# Función para obtener configuración de cliente desde el template
get_client_config() {
    local client_key="$1"
    
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")
    
    local template_file=""
    
    if [[ -n "$config_class_name" ]]; then
        template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    fi
    
    if [[ -z "$template_file" || ! -f "$template_file" ]]; then
        return 1
    fi
    
    php -r "
        \$content = file_get_contents('$template_file');
        
        if (preg_match(\"/public const CLIENT_ID = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT_ID=' . \$matches[1] . '|';
        }
        
        if (preg_match(\"/public const CLIENT = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT=' . \$matches[1] . '|';
        }
        
        if (preg_match(\"/public const COUNTRY_CODE = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_CODE=' . \$matches[1] . '|';
        }
        
        if (preg_match(\"/public const COUNTRY_NAME = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_NAME=' . \$matches[1] . '|';
        }
        
        if (preg_match(\"/public const IMAGE = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'IMAGE=' . \$matches[1] . '|';
        }
        
        if ('$config_class_name' !== '') {
            echo 'TEMPLATE_FILE=' . '$config_class_name' . '|';
        }
    " 2>/dev/null || echo ""
}

# Función para obtener todos los clientes disponibles
get_all_clients() {
    if [[ -f "$CONFIG_FILE" ]]; then
        php -r "
            \$config = include '$CONFIG_FILE';
            if (is_array(\$config) && isset(\$config[0])) {
                echo implode(' ', \$config);
            } else {
                echo implode(' ', array_keys(\$config));
            }
        " 2>/dev/null && return
    fi
    
    local templates_dir="${BASE_DIR}/config/templates"
    if [[ -d "$templates_dir" ]]; then
        for file in "$templates_dir"/*Config.php; do
            [[ -f "$file" ]] || continue
            local basename=$(basename "$file" .php)
            case "$basename" in
                "PlacetopayColombiaConfig") echo -n "placetopay_colombia " ;;
                "PlacetopayEcuadorConfig") echo -n "placetopay_ecuador " ;;
                "PlacetopayBeliceConfig") echo -n "placetopay_belice " ;;
                "GetnetChileConfig") echo -n "getnet_chile " ;;
                "PlacetopayHondurasConfig") echo -n "placetopay_honduras " ;;
                "PlacetopayUruguayConfig") echo -n "placetopay_uruguay " ;;
                "AvalpayColombiaConfig") echo -n "avalpay_colombia " ;;
                "BanchileChileConfig") echo -n "banchile_chile " ;;
                *)
                    echo -n "$(echo "$basename" | sed 's/Config$//' | sed 's/\([A-Z]\)/-\1/g' | sed 's/^-//' | tr '[:upper:]' '[:lower:]') "
                    ;;
            esac
        done
        echo ""
    fi
}

# Función para parsear configuración
parse_config() {
    local config="$1"

    CLIENT_ID=""
    CLIENT=""
    COUNTRY_CODE=""
    COUNTRY_NAME=""
    TEMPLATE_FILE=""
    IMAGE=""

    IFS='|' read -ra PARTS <<< "$config"
    for part in "${PARTS[@]}"; do
        IFS='=' read -ra KV <<< "$part"
        local key="${KV[0]}"
        local value="${KV[1]}"

        case "$key" in
            "CLIENT_ID") CLIENT_ID="$value" ;;
            "CLIENT") CLIENT="$value" ;;
            "COUNTRY_CODE") COUNTRY_CODE="$value" ;;
            "COUNTRY_NAME") COUNTRY_NAME="$value" ;;
            "TEMPLATE_FILE") TEMPLATE_FILE="$value" ;;
            "IMAGE") IMAGE="$value" ;;
        esac
    done
}

# Función para obtener el nombre del namespace desde CLIENT_ID
# CLIENT_ID ahora viene con guiones bajos (ej: banchile_chile)
get_namespace_name() {
    local client_id="$1"
    
    echo "$client_id" | awk -F'_' '{
        result = ""
        for (i=1; i<=NF; i++) {
            word = $i
            if (length(word) > 0) {
                first = toupper(substr(word,1,1))
                rest = tolower(substr(word,2))
                result = result first rest
            }
        }
        print result
    }'
}

# Función para obtener el nombre del módulo Magento (Vendor_ModuleName)
get_module_name() {
    local namespace_name="$1"
    echo "${namespace_name}_Payments"
}

# Función para reemplazar namespaces en archivos PHP
replace_namespaces() {
    local work_dir="$1"
    local new_namespace="$2"
    
    print_status "Reemplazando namespaces: PlacetoPay\\Payments -> ${new_namespace}\\Payments"
    
    local old_ns="PlacetoPay\\\\Payments"
    local new_ns="${new_namespace}\\\\Payments"
    
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|namespace PlacetoPay\\\\Payments|namespace ${new_namespace}\\\\Payments|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|use PlacetoPay\\\\Payments|use ${new_namespace}\\\\Payments|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|\\\\PlacetoPay\\\\Payments|\\\\${new_namespace}\\\\Payments|g" {} \;
    
    find "$work_dir" -type f -name "*.bak" -delete
}

# Función para reemplazar nombres de módulo en XML y registration.php
replace_module_names() {
    local work_dir="$1"
    local old_module="PlacetoPay_Payments"
    local new_module="$2"
    
    print_status "Reemplazando nombre del módulo: $old_module -> $new_module"
    
    # Reemplazar en registration.php
    if [[ -f "$work_dir/registration.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|'${old_module}'|'${new_module}'|g" "$work_dir/registration.php"
        else
            sed -i "s|'${old_module}'|'${new_module}'|g" "$work_dir/registration.php"
        fi
    fi
    
    # Reemplazar en module.xml
    find "$work_dir/etc" -type f -name "module.xml" -exec sed -i.bak "s|<module name=\"${old_module}\"|<module name=\"${new_module}\"|g" {} \;
    
    # Reemplazar en todos los XML (etc y view)
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|${old_module}|${new_module}|g" {} \;
    find "$work_dir/view" -type f -name "*.xml" -exec sed -i.bak "s|${old_module}|${new_module}|g" {} \;
    
    # Reemplazar en archivos JS
    find "$work_dir/view" -type f -name "*.js" -exec sed -i.bak "s|${old_module}|${new_module}|g" {} \;
    
    # Reemplazar en archivos PHTML
    find "$work_dir/view" -type f -name "*.phtml" -exec sed -i.bak "s|${old_module}|${new_module}|g" {} \;
    
    find "$work_dir" -type f -name "*.bak" -delete
}

# Función para convertir client_id a formato válido para XML (snake_case)
get_xml_safe_id() {
    local client_id="$1"
    # CLIENT_ID ya viene con guiones bajos desde los templates, así que devolverlo tal cual
    echo "$client_id"
}

# Función para reemplazar payment method code y constantes
replace_payment_codes() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name="$3"
    local client_name="$4"  # nombre del cliente para reemplazos en textos
    local client_key="$5"   # client_key para obtener la configuración (IMAGE, etc.)
    
    # Obtener versión snake_case para IDs de XML
    local xml_safe_id
    xml_safe_id=$(get_xml_safe_id "$client_id")
    
    print_status "Reemplazando payment method code: placetopay -> ${client_id} (XML safe: ${xml_safe_id})"
    
    # Obtener payment_method_name usando xml_safe_id (client_id con guiones convertidos a guiones bajos)
    # Ejemplo: banchile-chile -> banchile_chile
    local payment_method_name
    payment_method_name="$xml_safe_id"
    
    # Reemplazar CODE en PaymentMethod.php con payment_method_name (CLIENT_ID con guiones bajos)
    # Esto es necesario porque PHP no permite inicializar constantes con otras constantes de otras clases
    find "$work_dir" -type f -name "PaymentMethod.php" -exec sed -i.bak "s|public const CODE = 'placetopay';|public const CODE = '${payment_method_name}';|g" {} \;
    
    # Reemplazar variable $placetopay en Data.php con el nombre del método
    # Convertir payment_method_name a formato camelCase para la variable (banchile_chile -> BanchileChile)
    local var_name
    var_name=$(echo "$payment_method_name" | awk -F'_' '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) tolower(substr($i,2)); print}' OFS='')
    find "$work_dir/Controller/Payment" -type f -name "Data.php" -exec sed -i.bak \
        -e "s|\$placetopay =|\$${var_name} =|g" \
        -e "s|\$placetopay->|\$${var_name}->|g" \
        -e "s|@var PaymentMethod.*\$placetopay|@var PaymentMethod \$${var_name}|g" \
        {} \;
    
    # Reemplazar referencias a payment/placetopay/ en Helper/Data.php y otros archivos PHP
    # Usar xml_safe_id para las rutas de configuración
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|'payment/placetopay/|'payment/${xml_safe_id}/|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|\"payment/placetopay/|\"payment/${xml_safe_id}/|g" {} \;
    
    # Reemplazar nombres de campos que tienen placetopay_ como prefijo
    # Ej: payment/placetopay/placetopay_mode -> payment/{xml_safe_id}/{xml_safe_id}_mode
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|/${xml_safe_id}/placetopay_|/${xml_safe_id}/${xml_safe_id}_|g" {} \;
    
    # Usar xml_safe_id (client_id con guiones convertidos a guiones bajos) para payment method code
    # Ejemplo: banchile-chile -> banchile_chile
    local payment_method_name
    payment_method_name="$xml_safe_id"
    
    # Reemplazar en XML - usar payment_method_name (xml_safe_id) para el tag de payment
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|<placetopay>|<${payment_method_name}>|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|</placetopay>|</${payment_method_name}>|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|payment/placetopay|payment/${xml_safe_id}|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|placetopay_|${xml_safe_id}_|g" {} \;
    
    # Reemplazar nombre del método en payment.xml (usar xml_safe_id)
    find "$work_dir/etc" -type f -name "payment.xml" -exec sed -i.bak "s|<method name=\"placetopay\">|<method name=\"${payment_method_name}\">|g" {} \;
    
    # Reemplazar en archivos de layout (checkout_index_index.xml, etc.)
    # Reemplazar el nombre del método de pago en los layouts
    find "$work_dir/view" -type f -name "*.xml" -exec sed -i.bak "s|<item name=\"placetopay\"|<item name=\"${payment_method_name}\"|g" {} \;
    find "$work_dir/view" -type f -name "*.xml" -exec sed -i.bak "s|name=\"placetopay\"|name=\"${payment_method_name}\"|g" {} \;
    
    # Reemplazar paths de componente JS en layouts
    find "$work_dir/view" -type f -name "*.xml" -exec sed -i.bak "s|PlacetoPay_Payments/js/view/payment/placetopay|${namespace_name}_Payments/js/view/payment/${payment_method_name}|g" {} \;
    
    # Reemplazar url.build y window.checkoutConfig en archivos JS ANTES de renombrar (importante hacerlo aquí)
    find "$work_dir/view" -type f -name "*.js" -exec sed -i.bak \
        -e "s|url\.build('placetopay/payment/data')|url.build('${xml_safe_id}/payment/data')|g" \
        -e "s|url\.build(\"placetopay/payment/data\")|url.build(\"${xml_safe_id}/payment/data\")|g" \
        -e "s|window\.checkoutConfig\.payment\.placetopay|window.checkoutConfig.payment.${payment_method_name}|g" \
        {} \;
    
    # Reemplazar rutas (frontName, route id, URLs) - usar xml_safe_id (client_id con guiones bajos)
    # Magento requiere que las rutas no tengan guiones, solo guiones bajos
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|frontName=\"placetopay\"|frontName=\"${xml_safe_id}\"|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|route id=\"placetopay\"|route id=\"${xml_safe_id}\"|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|url=\"/V1/placetopay/|url=\"/V1/${xml_safe_id}/|g" {} \;
    
    # Reemplazar URL de retorno en PlacetoPayPayment.php (o el servicio renombrado)
    find "$work_dir" -type f -name "*Payment.php" -path "*/Service/*" -exec sed -i.bak \
        -e "s|getUrl('placetopay/payment/response|getUrl(str_replace('-', '_', CountryConfig::CLIENT_ID) . '/payment/response|g" \
        -e "s|getUrl(\"placetopay/payment/response|getUrl(str_replace('-', '_', CountryConfig::CLIENT_ID) . '/payment/response|g" \
        {} \;
    
    # Reemplazar nombres de eventos - usar xml_safe_id
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|name=\"placetopay_|name=\"${xml_safe_id}_|g" {} \;
    
    # Reemplazar nombres de grupos de cron - usar xml_safe_id
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|group id=\"placetopay\"|group id=\"${xml_safe_id}\"|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|name=\"placetopay_payments_cron\"|name=\"${xml_safe_id}_payments_cron\"|g" {} \;
    
    # Reemplazar en di.xml (logger name) - usar xml_safe_id
    # Reemplazar tanto en atributos como en valores de argumentos
    find "$work_dir/etc" -type f -name "di.xml" -exec sed -i.bak \
        -e "s|name=\"placetopay\"|name=\"${xml_safe_id}\"|g" \
        -e "s|<argument name=\"name\" xsi:type=\"string\">placetopay</argument>|<argument name=\"name\" xsi:type=\"string\">${xml_safe_id}</argument>|g" \
        {} \;
    
    # Reemplazar en di.xml (custom provider) - usar xml_safe_id
    find "$work_dir/etc" -type f -name "di.xml" -exec sed -i.bak "s|placetopay_custom_provider|${xml_safe_id}_custom_provider|g" {} \;
    
    # Reemplazar referencias a clases Block en XML (frontend_model, etc.)
    local namespace_name
    namespace_name=$(get_namespace_name "$client_id")
    # Reemplazar en etc/*.xml
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|PlacetoPay\\\\Payments\\\\Block|${namespace_name}\\\\Payments\\\\Block|g" {} \;
    # Reemplazar en view/*/layout/*.xml (layouts frontend y adminhtml)
    find "$work_dir/view" -type f -name "*.xml" -path "*/layout/*" -exec sed -i.bak "s|PlacetoPay\\\\Payments\\\\Block|${namespace_name}\\\\Payments\\\\Block|g" {} \;
    
    # Reemplazar referencias a source_model que usan PlacetoPay namespace
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|PlacetoPay\\\\Payments\\\\Model\\\\Adminhtml\\\\Source|${namespace_name}\\\\Payments\\\\Model\\\\Adminhtml\\\\Source|g" {} \;
    
    # Reemplazar referencias a Logger en di.xml
    find "$work_dir/etc" -type f -name "di.xml" -exec sed -i.bak "s|PlacetoPay\\\\Payments\\\\Logger|${namespace_name}\\\\Payments\\\\Logger|g" {} \;
    
    # Reemplazar referencias a Api, Plugin, Model, Observer, etc. en di.xml y otros XML
    # IMPORTANTE: También reemplazar en config.xml el modelo de pago
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak \
        -e "s|PlacetoPay\\\\Payments\\\\Api|${namespace_name}\\\\Payments\\\\Api|g" \
        -e "s|PlacetoPay\\\\Payments\\\\Plugin|${namespace_name}\\\\Payments\\\\Plugin|g" \
        -e "s|PlacetoPay\\\\Payments\\\\Model|${namespace_name}\\\\Payments\\\\Model|g" \
        -e "s|PlacetoPay\\\\Payments\\\\Observer|${namespace_name}\\\\Payments\\\\Observer|g" \
        -e "s|PlacetoPay\\\\Payments\\\\Cron|${namespace_name}\\\\Payments\\\\Cron|g" \
        -e "s|\\\\PlacetoPay\\\\Payments\\\\|\\\\${namespace_name}\\\\Payments\\\\|g" \
        -e "s|PlacetoPay\\Payments\\Model|${namespace_name}\\Payments\\Model|g" \
        {} \;
    
    # Reemplazar textos de "Placetopay" en comentarios, labels, CDATA, etc. en XML
    # El nombre del cliente ya viene como parámetro
    if [[ -z "$client_name" ]]; then
        print_error "El nombre del cliente no puede estar vacío"
        return 1
    fi
    
    local client_name_url
    client_name_url=$(get_url_safe_name "$client_name")
    local client_name_lower
    client_name_lower=$(to_lowercase "$client_name")
    # Convertir nombre del cliente a formato válido para CSS (sin espacios, solo guiones)
    local client_name_css
    client_name_css=$(echo "$client_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    
    # Obtener la URL de la imagen del logo desde CountryConfig usando client_key
    local image_url=""
    if [[ -n "$client_key" ]]; then
        local client_config
        client_config=$(get_client_config "$client_key")
        if [[ -n "$client_config" ]]; then
            # parse_config establece variables globales, pero necesitamos IMAGE
            parse_config "$client_config"
            image_url="$IMAGE"
        fi
    fi
    
    print_status "Reemplazando textos de Placetopay en archivos XML con: $client_name"
    if [[ -n "$image_url" ]]; then
        print_status "Logo del cliente: $image_url"
    else
        print_status "No se encontró URL del logo en la configuración"
    fi
    
    # Reemplazar "Placetopay" en textos de XML (labels, comments, CDATA)
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|Placetopay|${client_name}|g" {} \;
    
    # Reemplazar "placetopay" en minúsculas (fieldset_css, URLs, etc.)
    # Usar client_name_css para que coincida con el CSS
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|placetopay-admin-config|${client_name_css}-admin-config|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|placetopay\.com|${client_name_url}.com|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|panel.placetopay.com|panel.${client_name_url}.com|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|www.placetopay.com|www.${client_name_url}.com|g" {} \;
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|placetopay team|${client_name_lower} team|g" {} \;
    
    # Corregir URLs que quedaron con espacios
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak "s|${client_name}\.com|${client_name_url}.com|g" {} \;
    
    # Reemplazar en archivos CSS
    if [[ -n "$image_url" ]]; then
        print_status "Actualizando logo en CSS con: $image_url"
        # Convertir client_id a formato válido para CSS (snake_case)
        local client_id_css
        client_id_css=$(echo "$client_id" | tr '[:upper:]' '[:lower:]' | tr '-' '_' | tr ' ' '_')
        local heading_class="heading-${client_id_css}"
        
        # Reemplazar nombre de clase CSS (client_name_css ya está definido arriba)
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|\.placetopay-admin-config|\.${client_name_css}-admin-config|g" {} \;
        
        # Reemplazar todas las clases .heading y .heading-* con la clase única del cliente
        # Primero reemplazar .heading-cl y otras variantes específicas
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|\.heading-cl|\.${heading_class}|g" {} \;
        # Reemplazar .heading (sin sufijo) con la clase única, pero solo dentro del contexto de admin-config
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|\.${client_name_css}-admin-config \.heading {|\.${client_name_css}-admin-config \.${heading_class} {|g" {} \;
        # También reemplazar cualquier otra referencia a .heading que quede
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s| \.heading {| \.${heading_class} {|g" {} \;
        
        # Reemplazar URL del logo en todas las clases heading
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|url(\"https://static.placetopay.com/placetopay-logo.svg\")|url(\"${image_url}\")|g" {} \;
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|url('https://static.placetopay.com/placetopay-logo.svg')|url('${image_url}')|g" {} \;
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "s|url(\"https://banco.santander.cl.*getnet_logo.svg\")|url(\"${image_url}\")|g" {} \;
        
        # Asegurar que la clase única del cliente tenga la imagen correcta
        find "$work_dir/view" -type f -name "*.css" -exec sed -i.bak "/\.${client_name_css}-admin-config \.${heading_class} {/,/}/ s|url(\"[^\"]*\")|url(\"${image_url}\")|g" {} \;
    fi
    
    # Convertir guiones a guiones bajos en todos los atributos XML (id, config_path, etc.)
    # Esto asegura que todos los IDs y rutas cumplan con el patrón [a-zA-Z0-9_]+
    print_status "Convirtiendo guiones a guiones bajos en atributos XML..."
    find "$work_dir/etc" -type f -name "*.xml" -exec sed -i.bak \
        -e "s|id=\"${xml_safe_id}-|id=\"${xml_safe_id}_|g" \
        -e "s|id=\"${client_id}\"|id=\"${xml_safe_id}\"|g" \
        -e "s|config_path=\"payment/${client_id}/|config_path=\"payment/${xml_safe_id}/|g" \
        -e "s|field id=\"${client_id}_|field id=\"${xml_safe_id}_|g" \
        -e "s|<field id=\"${client_id}\"|<field id=\"${xml_safe_id}\"|g" \
        -e "s|<${client_id}>|<${xml_safe_id}>|g" \
        -e "s|</${client_id}>|</${xml_safe_id}>|g" \
        {} \;
    
    find "$work_dir" -type f -name "*.bak" -delete
}

# Función para renombrar archivos JS/CSS y layouts
rename_view_files() {
    local work_dir="$1"
    local payment_method_name="$2"
    local namespace_name="$3"
    local client_id="$4"  # Agregar client_id como parámetro
    
    print_status "Renombrando archivos JS/CSS y layouts con payment_method_name: ${payment_method_name}..."
    
    # Renombrar archivos JS (puede que ya tengan nombre antiguo como banchile_chile.js)
    if [[ -f "$work_dir/view/frontend/web/js/view/payment/placetopay.js" ]]; then
        mv "$work_dir/view/frontend/web/js/view/payment/placetopay.js" "$work_dir/view/frontend/web/js/view/payment/${payment_method_name}.js"
    elif [[ -f "$work_dir/view/frontend/web/js/view/payment/banchile_chile.js" ]]; then
        # Si ya se renombró con el nombre antiguo, renombrarlo de nuevo
        mv "$work_dir/view/frontend/web/js/view/payment/banchile_chile.js" "$work_dir/view/frontend/web/js/view/payment/${payment_method_name}.js"
    fi
    
    if [[ -f "$work_dir/view/frontend/web/js/view/payment/method-renderer/placetopay.js" ]]; then
        mv "$work_dir/view/frontend/web/js/view/payment/method-renderer/placetopay.js" "$work_dir/view/frontend/web/js/view/payment/method-renderer/${payment_method_name}.js"
    elif [[ -f "$work_dir/view/frontend/web/js/view/payment/method-renderer/banchile_chile.js" ]]; then
        # Si ya se renombró con el nombre antiguo, renombrarlo de nuevo
        mv "$work_dir/view/frontend/web/js/view/payment/method-renderer/banchile_chile.js" "$work_dir/view/frontend/web/js/view/payment/method-renderer/${payment_method_name}.js"
    fi
    
    # Renombrar archivos CSS
    if [[ -f "$work_dir/view/frontend/web/css/placetopay.css" ]]; then
        mv "$work_dir/view/frontend/web/css/placetopay.css" "$work_dir/view/frontend/web/css/${payment_method_name}.css"
        # Actualizar referencia en default.xml
        find "$work_dir/view/frontend/layout" -type f -name "default.xml" -exec sed -i.bak "s|css/placetopay\.css|css/${payment_method_name}.css|g" {} \;
        
        # Reemplazar clases CSS en el archivo renombrado
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' \
                -e "s|\.placetopay-method-message|\.${payment_method_name}-method-message|g" \
                -e "s|\.placetopay-method-message-warning|\.${payment_method_name}-method-message-warning|g" \
                -e "s|\.placetopay-method-message-text|\.${payment_method_name}-method-message-text|g" \
                -e "s|\.placetopay-method-message-recommendation|\.${payment_method_name}-method-message-recommendation|g" \
                -e "s|\.placetopay-icon-container|\.${payment_method_name}-icon-container|g" \
                -e "s|\.placetopay-checkout-onepage|\.${payment_method_name}-checkout-onepage|g" \
                -e "s|\.placetopay-onepage-success|\.${payment_method_name}-onepage-success|g" \
                "$work_dir/view/frontend/web/css/${payment_method_name}.css"
        else
            sed -i \
                -e "s|\.placetopay-method-message|\.${payment_method_name}-method-message|g" \
                -e "s|\.placetopay-method-message-warning|\.${payment_method_name}-method-message-warning|g" \
                -e "s|\.placetopay-method-message-text|\.${payment_method_name}-method-message-text|g" \
                -e "s|\.placetopay-method-message-recommendation|\.${payment_method_name}-method-message-recommendation|g" \
                -e "s|\.placetopay-icon-container|\.${payment_method_name}-icon-container|g" \
                -e "s|\.placetopay-checkout-onepage|\.${payment_method_name}-checkout-onepage|g" \
                -e "s|\.placetopay-onepage-success|\.${payment_method_name}-onepage-success|g" \
                "$work_dir/view/frontend/web/css/${payment_method_name}.css"
        fi
    fi
    
    # Renombrar templates HTML
    if [[ -f "$work_dir/view/frontend/web/template/payment/placetopay.html" ]]; then
        mv "$work_dir/view/frontend/web/template/payment/placetopay.html" "$work_dir/view/frontend/web/template/payment/${payment_method_name}.html"
        
        # Reemplazar clases CSS e IDs en el archivo HTML renombrado
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' \
                -e "s|id=\"payment-method-placetopay\"|id=\"payment-method-${payment_method_name}\"|g" \
                -e "s|\.placetopay-method-logo|\.${payment_method_name}-method-logo|g" \
                -e "s|class=\"placetopay-method-logo\"|class=\"${payment_method_name}-method-logo\"|g" \
                -e "s|placetopay-icon-container|${payment_method_name}-icon-container|g" \
                -e "s|placetopay-method-description|${payment_method_name}-method-description|g" \
                -e "s|placetopay-method-message|${payment_method_name}-method-message|g" \
                -e "s|placetopay-method-message-warning|${payment_method_name}-method-message-warning|g" \
                -e "s|placetopay-method-message-text|${payment_method_name}-method-message-text|g" \
                -e "s|placetopay-method-message-recommendation|${payment_method_name}-method-message-recommendation|g" \
                "$work_dir/view/frontend/web/template/payment/${payment_method_name}.html"
        else
            sed -i \
                -e "s|id=\"payment-method-placetopay\"|id=\"payment-method-${payment_method_name}\"|g" \
                -e "s|\.placetopay-method-logo|\.${payment_method_name}-method-logo|g" \
                -e "s|class=\"placetopay-method-logo\"|class=\"${payment_method_name}-method-logo\"|g" \
                -e "s|placetopay-icon-container|${payment_method_name}-icon-container|g" \
                -e "s|placetopay-method-description|${payment_method_name}-method-description|g" \
                -e "s|placetopay-method-message|${payment_method_name}-method-message|g" \
                -e "s|placetopay-method-message-warning|${payment_method_name}-method-message-warning|g" \
                -e "s|placetopay-method-message-text|${payment_method_name}-method-message-text|g" \
                -e "s|placetopay-method-message-recommendation|${payment_method_name}-method-message-recommendation|g" \
                "$work_dir/view/frontend/web/template/payment/${payment_method_name}.html"
        fi
    fi
    
    # Actualizar referencias en archivos JS después de renombrar
    if [[ -f "$work_dir/view/frontend/web/js/view/payment/${payment_method_name}.js" ]]; then
        # Reemplazar el type y el component path
        sed -i.bak \
            -e "s|type: 'placetopay'|type: '${payment_method_name}'|g" \
            -e "s|type: \"placetopay\"|type: \"${payment_method_name}\"|g" \
            -e "s|method-renderer/placetopay|method-renderer/${payment_method_name}|g" \
            -e "s|PlacetoPay_Payments/payment/placetopay|${namespace_name}_Payments/payment/${payment_method_name}|g" \
            "$work_dir/view/frontend/web/js/view/payment/${payment_method_name}.js"
    fi
    
    if [[ -f "$work_dir/view/frontend/web/js/view/payment/method-renderer/${payment_method_name}.js" ]]; then
        # Reemplazar rutas de payment/data usando xml_safe_id (client_id con guiones bajos)
        # El patrón puede ser 'placetopay/payment/data' o '/payment/data' (si ya se reemplazó parcialmente)
        # Usar comillas simples y dobles para capturar ambos casos
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' \
                -e "s|url\.build('placetopay/payment/data')|url.build('${xml_safe_id}/payment/data')|g" \
                -e "s|url\.build(\"placetopay/payment/data\")|url.build(\"${xml_safe_id}/payment/data\")|g" \
                -e "s|url\.build('/payment/data')|url.build('${xml_safe_id}/payment/data')|g" \
                -e "s|url\.build(\"/payment/data\")|url.build(\"${xml_safe_id}/payment/data\")|g" \
                -e "s|payment/placetopay|payment/${payment_method_name}|g" \
                -e "s|PlacetoPay_Payments/payment/placetopay|${namespace_name}_Payments/payment/${payment_method_name}|g" \
                "$work_dir/view/frontend/web/js/view/payment/method-renderer/${payment_method_name}.js"
        else
            sed -i \
                -e "s|url\.build('placetopay/payment/data')|url.build('${xml_safe_id}/payment/data')|g" \
                -e "s|url\.build(\"placetopay/payment/data\")|url.build(\"${xml_safe_id}/payment/data\")|g" \
                -e "s|url\.build('/payment/data')|url.build('${xml_safe_id}/payment/data')|g" \
                -e "s|url\.build(\"/payment/data\")|url.build(\"${xml_safe_id}/payment/data\")|g" \
                -e "s|payment/placetopay|payment/${payment_method_name}|g" \
                -e "s|PlacetoPay_Payments/payment/placetopay|${namespace_name}_Payments/payment/${payment_method_name}|g" \
                "$work_dir/view/frontend/web/js/view/payment/method-renderer/${payment_method_name}.js"
        fi
    fi
    
    # Actualizar referencias en layouts después de renombrar
    find "$work_dir/view/frontend/layout" -type f -name "*.xml" -exec sed -i.bak "s|/payment/placetopay|/payment/${payment_method_name}|g" {} \;
    
    # Reemplazar clases CSS en archivos PHTML de templates
    find "$work_dir/view/frontend/templates" -type f -name "*.phtml" -exec sed -i.bak \
        -e "s|placetopay-checkout-onepage|${payment_method_name}-checkout-onepage|g" \
        -e "s|placetopay-onepage-success|${payment_method_name}-onepage-success|g" \
        -e "s|placetopay-onepage-pending|${payment_method_name}-onepage-pending|g" \
        -e "s|placetopay-onepage-failure|${payment_method_name}-onepage-failure|g" \
        {} \;
    
    # Renombrar archivos de layout XML
    if [[ -f "$work_dir/view/frontend/layout/placetopay_onepage_success.xml" ]]; then
        mv "$work_dir/view/frontend/layout/placetopay_onepage_success.xml" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_success.xml"
        # Actualizar referencias dentro del archivo
        sed -i.bak "s|placetopay\.checkout\.success|${payment_method_name}.checkout.success|g" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_success.xml"
    fi
    
    if [[ -f "$work_dir/view/frontend/layout/placetopay_onepage_pending.xml" ]]; then
        mv "$work_dir/view/frontend/layout/placetopay_onepage_pending.xml" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_pending.xml"
        sed -i.bak "s|placetopay\.checkout\.pending|${payment_method_name}.checkout.pending|g" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_pending.xml"
    fi
    
    if [[ -f "$work_dir/view/frontend/layout/placetopay_onepage_failure.xml" ]]; then
        mv "$work_dir/view/frontend/layout/placetopay_onepage_failure.xml" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_failure.xml"
        sed -i.bak "s|placetopay\.checkout\.failure|${payment_method_name}.checkout.failure|g" "$work_dir/view/frontend/layout/${payment_method_name}_onepage_failure.xml"
    fi
    
    find "$work_dir" -type f -name "*.bak" -delete
}

# Función para renombrar PlacetoPayService y PlacetoPayPayment
rename_service_classes() {
    local work_dir="$1"
    local namespace_name="$2"
    
    print_status "Renombrando PlacetoPayService a ${namespace_name}Service..."
    
    local service_dir="$work_dir/PlacetoPayService"
    local new_service_dir="$work_dir/${namespace_name}Service"
    
    # Renombrar directorio
    if [[ -d "$service_dir" ]]; then
        mv "$service_dir" "$new_service_dir"
    fi
    
    # Renombrar archivo PlacetoPayPayment.php
    if [[ -f "$new_service_dir/PlacetoPayPayment.php" ]]; then
        mv "$new_service_dir/PlacetoPayPayment.php" "$new_service_dir/${namespace_name}Payment.php"
    fi
    
    # Reemplazar namespace y clase en el archivo renombrado
    if [[ -f "$new_service_dir/${namespace_name}Payment.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # Reemplazar namespace (antes del reemplazo general de namespaces)
            sed -i '' "s|namespace PlacetoPay\\\\Payments\\\\PlacetoPayService|namespace ${namespace_name}\\\\Payments\\\\${namespace_name}Service|g" "$new_service_dir/${namespace_name}Payment.php"
            # Reemplazar nombre de clase
            sed -i '' "s|^class PlacetoPayPayment|class ${namespace_name}Payment|g" "$new_service_dir/${namespace_name}Payment.php"
            sed -i '' "s| class PlacetoPayPayment| class ${namespace_name}Payment|g" "$new_service_dir/${namespace_name}Payment.php"
        else
            sed -i "s|namespace PlacetoPay\\\\Payments\\\\PlacetoPayService|namespace ${namespace_name}\\\\Payments\\\\${namespace_name}Service|g" "$new_service_dir/${namespace_name}Payment.php"
            sed -i "s|^class PlacetoPayPayment|class ${namespace_name}Payment|g" "$new_service_dir/${namespace_name}Payment.php"
            sed -i "s| class PlacetoPayPayment| class ${namespace_name}Payment|g" "$new_service_dir/${namespace_name}Payment.php"
        fi
    fi
    
    # Reemplazar referencias en otros archivos
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|PlacetoPay\\\\Payments\\\\PlacetoPayService\\\\PlacetoPayPayment|${namespace_name}\\\\Payments\\\\${namespace_name}Service\\\\${namespace_name}Payment|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|use PlacetoPay\\\\Payments\\\\PlacetoPayService\\\\PlacetoPayPayment|use ${namespace_name}\\\\Payments\\\\${namespace_name}Service\\\\${namespace_name}Payment|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|new PlacetoPayPayment(|new ${namespace_name}Payment(|g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|PlacetoPayPayment |${namespace_name}Payment |g" {} \;
    find "$work_dir" -type f -name "*.php" -exec sed -i.bak "s|@var PlacetoPayPayment|@var ${namespace_name}Payment|g" {} \;
    
    find "$work_dir" -type f -name "*.bak" -delete
}

# Función para convertir a minúsculas (compatible con macOS y Linux)
to_lowercase() {
    echo "$1" | tr '[:upper:]' '[:lower:]'
}

# Función para convertir nombre del cliente a formato URL (sin espacios, minúsculas)
get_url_safe_name() {
    local client_name="$1"
    # Convertir a minúsculas y reemplazar espacios con guiones
    echo "$client_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-'
}

# Función para actualizar traducciones con el nombre del cliente
update_translations() {
    local work_dir="$1"
    local client_name="$2"
    local client_name_lower
    client_name_lower=$(to_lowercase "$client_name")
    local client_name_url
    client_name_url=$(get_url_safe_name "$client_name")
    
    print_status "Actualizando traducciones con nombre del cliente: $client_name"
    
    # Reemplazar "Pay by Card (Placetopay)" con solo el nombre del cliente (ej: "Banchile pagos")
    # Esto debe hacerse ANTES del reemplazo general de "Placetopay" para evitar conflictos
    if [[ "$OSTYPE" == "darwin"* ]]; then
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|Pay by Card (Placetopay)|${client_name}|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|\"Pay by Card (Placetopay)\"|\"${client_name}\"|g" {} \;
    else
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|Pay by Card (Placetopay)|${client_name}|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|\"Pay by Card (Placetopay)\"|\"${client_name}\"|g" {} \;
    fi
    
    # Reemplazar "Placetopay" con el nombre del cliente en archivos CSV de traducción
    # IMPORTANTE: Reemplazar TODAS las ocurrencias de "Placetopay" (tanto en inglés como en español)
    # También reemplazar nombres específicos de clientes que puedan estar en las traducciones (Getnet, etc.)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # Reemplazar "Placetopay" en todas las ocurrencias (incluyendo ambas columnas del CSV)
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|Placetopay|${client_name}|g" {} \;
        # Reemplazar nombres específicos de clientes que puedan estar en traducciones
        # (Getnet, etc.) - solo si no es el cliente actual
        # Usar un reemplazo más agresivo que capture todas las ocurrencias
        if [[ "$client_name" != "Getnet" ]]; then
            find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|Getnet|${client_name}|g" {} \;
        fi
        # Reemplazar "placetopay" en minúsculas (URLs, etc.) - usar versión URL-safe
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|placetopay\.com|${client_name_url}.com|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|placetopay team|${client_name_lower} team|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|panel.placetopay.com|panel.${client_name_url}.com|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|www.placetopay.com|www.${client_name_url}.com|g" {} \;
        # Corregir URLs que quedaron con espacios (del reemplazo anterior)
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i '' "s|${client_name}\.com|${client_name_url}.com|g" {} \;
    else
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|Placetopay|${client_name}|g" {} \;
        if [[ "$client_name" != "Getnet" ]]; then
            find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|Getnet|${client_name}|g" {} \;
        fi
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|placetopay\.com|${client_name_url}.com|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|placetopay team|${client_name_lower} team|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|panel.placetopay.com|panel.${client_name_url}.com|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|www.placetopay.com|www.${client_name_url}.com|g" {} \;
        find "$work_dir/i18n" -type f -name "*.csv" -exec sed -i "s|${client_name}\.com|${client_name_url}.com|g" {} \;
    fi
}

# Función para crear CountryConfig desde template
create_country_config() {
    local target_file="$1"
    local client_key="$2"
    
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")
    
    local template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    
    if [[ ! -f "$template_file" ]]; then
        print_error "Template no encontrado: $template_file"
        return 1
    fi
    
    print_status "Copiando template CountryConfig: $config_class_name"
    cp "$template_file" "$target_file"
}

# Función para limpiar archivos innecesarios
cleanup_build_files() {
    local work_dir="$1"

    print_status "Limpiando archivos de desarrollo innecesarios..."

    find "$work_dir" -type d -name ".git*" -exec rm -rf {} + 2>/dev/null || true
    rm -rf "$work_dir/.git"*
    rm -rf "$work_dir/.idea"
    rm -rf "$work_dir/tmp"
    rm -rf "$work_dir/Dockerfile"
    rm -rf "$work_dir/Makefile"
    rm -rf "$work_dir/.env"*
    rm -rf "$work_dir/docker"*
    rm -rf "$work_dir/composer.lock"
    rm -rf "$work_dir/.php_cs.cache"
    rm -rf "$work_dir"/*.md
    rm -rf "$work_dir/builds"
    rm -rf "$work_dir/temp_builds"
    rm -rf "$work_dir/config"
    rm -rf "$work_dir"/*.sh
    rm -Rf "$work_dir/.phpactor.json"
    rm -Rf "$work_dir/.php-cs-fixer.cache"
    rm -Rf "$work_dir"/*.log
    rm -rf "$work_dir/composer.json"
    rm -rf "$work_dir/composer.lock"
    rm -rf "$work_dir/vendor"
}

# Función para crear versión de marca blanca
create_white_label_version() {
    local client_key="$1"
    # Normalizar client_key: convertir guiones a guiones bajos (los templates ahora usan guiones bajos)
    client_key=$(echo "$client_key" | tr '-' '_')
    
    local config
    config=$(get_client_config "$client_key")

    if [[ -z "$config" ]]; then
        print_error "Cliente desconocido: $client_key"
        return 1
    fi

    parse_config "$config"

    if [[ -n "$CLIENT_ID" ]]; then
        # CLIENT_ID ya viene con guiones bajos desde los templates, solo asegurar minúsculas
        CLIENT_ID=$(echo "$CLIENT_ID" | tr '[:upper:]' '[:lower:]')
    else
        print_error "CLIENT_ID no encontrado en la configuración"
        return 1
    fi

    local namespace_name
    namespace_name=$(get_namespace_name "$CLIENT_ID")
    
    local module_name
    module_name=$(get_module_name "$namespace_name")
    
    # Usar namespace_name para el nombre de la carpeta (PascalCase sin guiones)
    local project_name="${namespace_name}"

    print_status "Creando versión de marca blanca: $project_name"
    print_status "Cliente: $CLIENT, País: $COUNTRY_NAME ($COUNTRY_CODE)"

    # Crear directorio de trabajo temporal
    local work_dir="$TEMP_DIR/$project_name"
    mkdir -p "$work_dir"

    # Copiar archivos fuente (excluyendo builds, config, etc.)
    print_status "Copiando archivos fuente..."
    rsync -a \
        --exclude='builds/' \
        --exclude='temp_builds/' \
        --exclude='.git/' \
        --exclude='*.sh' \
        --exclude='config/' \
        --exclude='vendor/' \
        --exclude='composer.json' \
        --exclude='composer.lock' \
        --exclude='example woocommerce/' \
        "$BASE_DIR/" "$work_dir/" 2>/dev/null || true

    # Crear CountryConfig desde template
    print_status "Creando CountryConfig desde template..."
    create_country_config "$work_dir/CountryConfig.php" "$client_key"
    
    # Eliminar directorio Countries si existe (ya no se usa)
    rm -rf "$work_dir/Countries"

    # Renombrar PlacetoPayService y PlacetoPayPayment ANTES de reemplazar namespaces
    # (para que el reemplazo de namespaces también actualice las referencias al Service)
    rename_service_classes "$work_dir" "$namespace_name"

    # Reemplazar namespaces (esto también actualizará las referencias al Service renombrado)
    replace_namespaces "$work_dir" "$namespace_name"

    # Reemplazar nombres de módulo
    replace_module_names "$work_dir" "$module_name"

    # Reemplazar payment codes (pasar CLIENT para reemplazos en textos y client_key para obtener IMAGE)
    replace_payment_codes "$work_dir" "$CLIENT_ID" "$namespace_name" "$CLIENT" "$client_key"
    
    # Obtener payment_method_name usando xml_safe_id (client_id con guiones convertidos a guiones bajos)
    # Ejemplo: banchile-chile -> banchile_chile
    local payment_method_name
    payment_method_name=$(echo "$CLIENT_ID" | tr '-' '_')
    
    # Renombrar archivos JS/CSS y layouts después de reemplazar referencias
    rename_view_files "$work_dir" "$payment_method_name" "$namespace_name" "$CLIENT_ID"

    # Actualizar traducciones con el nombre del cliente
    update_translations "$work_dir" "$CLIENT"

    # Limpiar archivos innecesarios
    cleanup_build_files "$work_dir"

    # Crear estructura correcta para Magento (Vendor/ModuleName)
    # El módulo debe estar en: Vendor/ModuleName (ej: BanchileChile/Payments)
    local vendor_name="$namespace_name"
    local module_name="Payments"
    local magento_structure_dir="$TEMP_DIR/${vendor_name}_${module_name}_structure"
    mkdir -p "$magento_structure_dir/$vendor_name/$module_name"
    
    # Copiar todo el contenido del módulo a la estructura correcta
    print_status "Organizando estructura para Magento: $vendor_name/$module_name"
    cp -r "$work_dir"/* "$magento_structure_dir/$vendor_name/$module_name/" 2>/dev/null || true
    
    # Crear archivo ZIP con la estructura correcta
    print_status "Creando archivo ZIP..."
    mkdir -p "$OUTPUT_DIR"
    cd "$magento_structure_dir"
    zip -rq "$OUTPUT_DIR/$project_name.zip" "$vendor_name"
    cd "$BASE_DIR"

    # Limpiar directorios temporales
    rm -rf "$work_dir"
    rm -rf "$magento_structure_dir"

    print_success "Creado: $OUTPUT_DIR/$project_name.zip"
}

# Función principal
main() {
    print_status "Iniciando proceso de generación de marca blanca..."

    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        exit 1
    fi

    # Limpiar builds anteriores
    print_status "Limpiando builds anteriores..."
    rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
    mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"

    # Procesar cada cliente
    for client_key in $(get_all_clients); do
        print_status "========================================="
        print_status "Procesando cliente: $client_key"
        print_status "========================================="
        echo
        create_white_label_version "$client_key"
        echo
    done

    # Limpiar directorio temporal
    print_status "Limpiando archivos temporales..."
    rm -rf "$TEMP_DIR"

    print_success "¡Generación de marca blanca completada!"
    print_status "Los archivos generados están en: $OUTPUT_DIR"

    echo
    print_status "Versiones de marca blanca generadas:"
    ls -la "$OUTPUT_DIR"/*.zip 2>/dev/null | while read -r line; do
        echo "  $line"
    done || print_warning "No se encontraron archivos ZIP"
}

# Mostrar información de uso
usage() {
    echo "Uso: $0 [OPCIONES] [CLIENTE]"
    echo ""
    echo "Generar versiones de marca blanca del módulo Magento 2 PlacetoPay"
    echo ""
    echo "Opciones:"
    echo "  -h, --help    Mostrar este mensaje de ayuda"
    echo "  -l, --list    Listar configuraciones de clientes disponibles"
    echo "  CLIENTE       Generar solo para un cliente específico (opcional)"
    echo ""
    echo "Clientes disponibles:"
    for client in $(get_all_clients); do
        config=$(get_client_config "$client")
        if [[ -n "$config" ]]; then
            parse_config "$config"
            echo "  - $client: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
        fi
    done
}

# Manejar argumentos de línea de comandos
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -l|--list)
        echo "Configuraciones de clientes disponibles:"
        for client_key in $(get_all_clients); do
            config=$(get_client_config "$client_key")
            if [[ -n "$config" ]]; then
                parse_config "$config"
                echo "  $client_key: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
            fi
        done
        exit 0
        ;;
    "")
        main
        ;;
    *)
        # Normalizar client_key: convertir guiones a guiones bajos (los templates ahora usan guiones bajos)
        normalized_key=$(echo "$1" | tr '-' '_')
        config=$(get_client_config "$normalized_key")
        if [[ -n "$config" ]]; then
            print_status "Generando versión de marca blanca para: $1"
            rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
            mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"
            create_white_label_version "$normalized_key"
            rm -rf "$TEMP_DIR"
            print_success "¡Generación de marca blanca completada para $1!"
        else
            print_error "Opción desconocida: $1"
            echo ""
            usage
            exit 1
        fi
        ;;
esac

