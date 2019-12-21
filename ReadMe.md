## Платежный модуль UAPAY для CMS Magento 2.1.x-2.3.x

Тестировался модуль на CMS Magento 2.3.3

### Установка
1. Загрузить папку **UaPayPayment** на сервер сайта в папку **[корень_сайта]/app/code/**
2. В консоли(через SSH) перейдите в корень сайта и введите по очереди следующие команды:

* php bin/magento module:enable UaPayPayment_UaPay
* php bin/magento setup:upgrade
* php bin/magento setup:di:compile
* php bin/magento setup:static-content:deploy

Для требуемой локализации(ru_RU,en_US) команда имеет следующий вид:

* php bin/magento setup:static-content:deploy en_US

### Настройка
1. Получите данные для авторизации от сервиса UAPAY (*clientId, secretKey*).
2. В админ. панели сайта перейти во вкладку _**Store → Configuration → Sales → Payment Methods**_ 
(_**Магазин → Конфигурации → Продажи → Методы оплаты**_)
3. Находим в списке "UAPAY" открываем настройки и заполняем все необходимые поля.
