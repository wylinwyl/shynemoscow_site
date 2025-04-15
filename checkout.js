document.addEventListener("DOMContentLoaded", async function () {
    const form = document.getElementById("checkout-form");
    const totalAmount = document.getElementById("total-amount");
    const deliveryOptions = document.querySelectorAll('input[name="delivery"]');
    const cdekPoints = document.getElementById("cdek-points");
    let selectedPvz = null;
    let cdekMap = null;

    const urlParams = new URLSearchParams(window.location.search);
    let baseTotal = parseInt(urlParams.get("total")) || 0;
    console.log('Base total from URL:', baseTotal);

    async function initCdekWidget() {
        return new Promise((resolve, reject) => {
            if (typeof window.CDEKWidget === 'undefined') {
                console.error('CDEKWidget is not available');
                cdekPoints.innerHTML = '<p>Ошибка: виджет СДЭК недоступен</p>';
                reject(new Error('CDEK Widget script not loaded'));
                return;
            }

            ymaps.ready(function() {
                console.log('Yandex Maps API is ready');
                try {
                    cdekMap = new window.CDEKWidget({
                        root: 'cdek_map',
                        apiKey: 'fa4fd0b5-b6cb-41fd-84ed-fe1e3228804d',
                        servicePath: 'https://shynemoscow.ru/cdek-service.php',
                        from: { code: 270 }, // Мытищи
                        defaultLocation: 'Мытищи',
                        canChoose: true,
                        goods: [{
                            width: 10,
                            height: 10,
                            length: 10,
                            weight: 1
                        }],
                        lang: 'rus',
                        currency: 'RUB',
                        tariffs: {
                            office: [136, 138], // "Посылка склад-склад" и "Посылка дверь-склад"
                            door: [137, 139]    // "Посылка склад-дверь" и "Посылка дверь-дверь"
                        },
                        onChoose: function(info) {
                            selectedPvz = {
                                code: info.id || info.code || info.PVZ?.Code,
                                address: info.address || info.PVZ?.Address || info.name,
                                city: info.city || info.PVZ?.city,
                                cityCode: info.cityCode || info.PVZ?.city_code,
                                tariffCode: info.tariffCode || info.tariff_code || info.tariff || null,
                                price: info.price || null
                            };
                            console.log('Full info from onChoose:', info);
                            console.log('Selected PVZ after onChoose:', selectedPvz);
                            setTimeout(updateTotal, 500); // Ждем 500 мс для onCalculate
                        },
                        onReady: function() {
                            console.log('CDEK Widget loaded');
                        },
                        onCalculate: function(info) {
                            console.log('Delivery cost calculated by widget:', info);
                            if (info.office && info.office.length > 0 && selectedPvz) {
                                // Уточняем цену для выбранного тарифа
                                const selectedTariff = selectedPvz.tariffCode 
                                    ? info.office.find(t => t.tariff_code === selectedPvz.tariffCode)
                                    : info.office[0]; // Если tariffCode не выбран, берем первый
                                if (selectedTariff) {
                                    selectedPvz.price = selectedTariff.delivery_sum;
                                    selectedPvz.tariffCode = selectedTariff.tariff_code;
                                    console.log('Updated PVZ from onCalculate:', selectedPvz);
                                    updateTotal();
                                }
                            }
                        }
                    });
                    resolve(cdekMap);
                } catch (error) {
                    console.error('Error initializing CDEK Widget:', error);
                    cdekPoints.innerHTML = '<p>Ошибка загрузки карты: ' + error.message + '</p>';
                    reject(error);
                }
            });
        });
    }

    async function calculateShipping() {
        const deliveryType = document.querySelector('input[name="delivery"]:checked').value;
        console.log('Calculating shipping for:', deliveryType);
        
        if (deliveryType === "СДЭК" && selectedPvz) {
            if (selectedPvz.price !== null && selectedPvz.tariffCode) {
                console.log('Using price from widget:', selectedPvz.price, 'for tariff:', selectedPvz.tariffCode);
                return selectedPvz.price;
            }
            console.log('Price not available from widget, falling back to manual calculation');
            try {
                const tariffCode = selectedPvz.tariffCode || 136; // По умолчанию 136
                const response = await fetch('https://shynemoscow.ru/cdek-service.php?action=calculate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        from_location: { code: 270 },
                        to_location: {
                            code: selectedPvz.cityCode || 270,
                            delivery_point: selectedPvz.code
                        },
                        packages: [{
                            weight: 1000,
                            length: 10,
                            width: 10,
                            height: 10
                        }],
                        tariff_code: tariffCode
                    })
                });
                const text = await response.text();
                console.log('Raw response from calculate:', text);
                const result = JSON.parse(text);
                if (!response.ok) throw new Error(result.message || 'Calculation failed');
                const pvzTariff = result.tariff_codes.find(tariff => tariff.tariff_code === tariffCode);
                const cost = pvzTariff ? pvzTariff.delivery_sum : 0;
                console.log('Calculated shipping cost:', cost, 'for tariff:', tariffCode);
                return cost;
            } catch (error) {
                console.error('Ошибка расчета стоимости СДЭК:', error);
                return 0;
            }
        } else if (deliveryType === "Почта России") {
            console.log('Using fixed cost for Почта России: 300');
            return 300;
        }
        return 0;
    }

    async function updateTotal() {
        const deliveryType = document.querySelector('input[name="delivery"]:checked').value;
        
        if (deliveryType === "СДЭК") {
            cdekPoints.style.display = "block";
            if (!cdekMap) {
                await initCdekWidget().catch(error => {
                    console.error('Failed to init CDEK Widget:', error);
                });
            }
        } else {
            cdekPoints.style.display = "none";
        }

        const deliveryCost = await calculateShipping();
        const total = baseTotal + deliveryCost;
        console.log('Base total:', baseTotal, 'Delivery cost:', deliveryCost, 'Total:', total);
        totalAmount.textContent = total;

        if (deliveryType === "СДЭК" && deliveryCost === 0 && selectedPvz) {
            console.log('Не удалось посчитать стоимость доставки');
        }
    }

    deliveryOptions.forEach(option => option.addEventListener("change", updateTotal));
    updateTotal();

    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const formData = new FormData(form);
        const total = parseInt(totalAmount.textContent, 10) || 0;
        const cartItems = JSON.parse(localStorage.getItem("cart") || "[]");
        const data = {
            name: formData.get("name"),
            phone: formData.get("phone"),
            email: formData.get("email"),
            address: formData.get("address"),
            comment: formData.get("comment"),
            delivery: formData.get("delivery"),
            cdekPoint: selectedPvz ? {
                code: selectedPvz.code,
                address: selectedPvz.address,
                city: selectedPvz.city,
                tariffCode: selectedPvz.tariffCode
            } : null,
            total: total,
            items: cartItems
        };

        console.log('Form data to submit:', data);

        if (cartItems.length === 0) {
            alert("Корзина пуста! Добавьте товары перед оформлением заказа.");
            return;
        }

        if (data.delivery === "СДЭК" && total === baseTotal && selectedPvz) {
            alert("Не могу посчитать стоимость доставки для выбранного пункта выдачи.");
            return;
        }

        try {
            const response = await fetch("https://shynemoscow.ru/send-order.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            console.log('Response from send-order.php:', result);
            if (result.success) {
                alert("Заказ успешно отправлен!");
                localStorage.removeItem("cart");
                window.location.href = "index.html";
            } else {
                throw new Error(result.message || "Ошибка при отправке заказа");
            }
        } catch (error) {
            console.error('Ошибка отправки заказа:', error);
            alert("Ошибка: " + error.message);
        }
    });

    document.querySelector(".back-btn").addEventListener("click", () => {
        window.location.href = "index.html";
    });
});