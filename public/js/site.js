(() => {
    'use strict';

    const STORAGE_KEY = 'ganesha_cart_v1';

    const GaneshaCart = {
        load() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) {
                    return [];
                }
                const parsed = JSON.parse(raw);

                return Array.isArray(parsed) ? parsed : [];
            } catch {
                localStorage.removeItem(STORAGE_KEY);

                return [];
            }
        },

        save(items) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            window.dispatchEvent(new CustomEvent('ganesha-cart-changed'));
        },

        clearDate(date) {
            const items = this.load().filter((item) => item.date !== date);
            this.save(items);
        },

        removeItem(date, menuDayDishId) {
            const items = this.load().filter(
                (item) => !(item.date === date && item.menu_day_dish_id === menuDayDishId),
            );
            this.save(items);
        },

        itemsFromGroups(groups) {
            const items = [];
            for (const group of groups) {
                for (const item of group.items || []) {
                    items.push({
                        menu_day_dish_id: item.menu_day_dish_id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity,
                        date: group.pickup_date,
                    });
                }
            }

            return items;
        },

        formatRub(kopecks) {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                maximumFractionDigits: 0,
            }).format(kopecks / 100);
        },

        formatDate(isoDate) {
            const date = new Date(isoDate + 'T12:00:00');
            return new Intl.DateTimeFormat('ru-RU', {
                day: 'numeric',
                month: 'long',
            }).format(date);
        },

        portionsLabel(count) {
            if (count === 1) {
                return '1 порция';
            }
            if (count >= 2 && count <= 4) {
                return count + ' порции';
            }

            return count + ' порций';
        },

        pluralRu(count, one, few, many) {
            const mod100 = Math.abs(count) % 100;
            const mod10 = mod100 % 10;

            if (mod100 >= 11 && mod100 <= 14) {
                return many;
            }
            if (mod10 === 1) {
                return one;
            }
            if (mod10 >= 2 && mod10 <= 4) {
                return few;
            }

            return many;
        },
    };

    window.GaneshaCart = GaneshaCart;

    document.addEventListener('alpine:init', registerAlpineComponents);

    function registerAlpineComponents() {
        Alpine.data('ganeshaCartWidget', () => ({
            items: [],
            cartOpen: false,

            init() {
                this.reload();
                window.addEventListener('ganesha-cart-changed', () => this.reload());
            },

            reload() {
                this.items = GaneshaCart.load();
                if (this.items.length === 0) {
                    this.cartOpen = false;
                }
            },

            cartDates() {
                const dates = [...new Set(this.items.map((item) => item.date))];
                dates.sort();

                return dates;
            },

            itemsForDate(date) {
                return this.items.filter((item) => item.date === date);
            },

            cartTotalKopecks() {
                return this.items.reduce(
                    (sum, item) => sum + item.price * item.quantity,
                    0,
                );
            },

            cartTotalItems() {
                return this.items.reduce((sum, item) => sum + item.quantity, 0);
            },

            cartSummaryLabel() {
                const days = this.cartDates().length;
                const portions = GaneshaCart.portionsLabel(this.cartTotalItems());
                if (days <= 1) {
                    return portions;
                }

                return portions + ' · ' + days + ' ' + GaneshaCart.pluralRu(days, 'день', 'дня', 'дней');
            },

            formatRub(kopecks) {
                return GaneshaCart.formatRub(kopecks);
            },

            formatDate(isoDate) {
                return GaneshaCart.formatDate(isoDate);
            },

            canCheckout() {
                return this.items.length > 0;
            },

            submitPrepare(event) {
                this.$refs.itemsField.value = JSON.stringify(GaneshaCart.load());
            },
        }));

        Alpine.data('ganeshaMenu', (menuDays) => ({
            days: menuDays,
            activeDate: null,
            items: [],

            init() {
                this.activeDate = this.firstOrderableDate() ?? menuDays[0]?.date ?? null;
                this.items = GaneshaCart.load();
                if (this.items.length > 0) {
                    const lastDate = this.items[this.items.length - 1].date;
                    if (this.days.some((d) => d.date === lastDate)) {
                        this.activeDate = lastDate;
                    }
                }
                window.addEventListener('ganesha-cart-changed', () => {
                    this.items = GaneshaCart.load();
                });
            },

            firstOrderableDate() {
                const day = this.days.find((d) => d.orderable);

                return day ? day.date : null;
            },

            activeDay() {
                return this.days.find((d) => d.date === this.activeDate) ?? null;
            },

            persistCart() {
                GaneshaCart.save(this.items);
            },

            selectDay(date) {
                this.activeDate = date;
            },

            quantityFor(dishId) {
                const item = this.items.find(
                    (i) => i.menu_day_dish_id === dishId && i.date === this.activeDate,
                );

                return item ? item.quantity : 0;
            },

            addDishById(dishId) {
                const day = this.activeDay();
                if (!day || !day.orderable) {
                    return;
                }

                const dish = (day.dishes || []).find((d) => d.menu_day_dish_id === dishId);
                if (!dish) {
                    return;
                }

                this.addDish({
                    menu_day_dish_id: dish.menu_day_dish_id,
                    name: dish.name,
                    price: dish.price,
                });
            },

            addDish(dish) {
                const day = this.activeDay();
                if (!day || !day.orderable) {
                    return;
                }

                const existing = this.items.find(
                    (i) => i.menu_day_dish_id === dish.menu_day_dish_id && i.date === this.activeDate,
                );

                if (existing) {
                    existing.quantity += 1;
                } else {
                    this.items.push({
                        menu_day_dish_id: dish.menu_day_dish_id,
                        name: dish.name,
                        price: dish.price,
                        quantity: 1,
                        date: this.activeDate,
                    });
                }

                this.persistCart();
            },

            decrementDish(dishId) {
                const index = this.items.findIndex(
                    (i) => i.menu_day_dish_id === dishId && i.date === this.activeDate,
                );
                if (index === -1) {
                    return;
                }

                if (this.items[index].quantity <= 1) {
                    this.items.splice(index, 1);
                } else {
                    this.items[index].quantity -= 1;
                }

                this.persistCart();
            },

            formatRub(kopecks) {
                return GaneshaCart.formatRub(kopecks);
            },

            orderableDaysCount() {
                return this.days.filter((day) => day.orderable).length;
            },

            addWeekMenu() {
                const orderableDays = this.days.filter((day) => day.orderable);
                if (orderableDays.length === 0) {
                    return;
                }

                const dishCount = orderableDays.reduce(
                    (sum, day) => sum + (day.dishes || []).length,
                    0,
                );

                const message = dishCount === 0
                    ? 'Нет доступных блюд на эту неделю.'
                    : 'Добавить по 1 порции каждого блюда на все доступные дни (' + orderableDays.length + ')?';

                if (dishCount === 0 || !window.confirm(message)) {
                    return;
                }

                for (const day of orderableDays) {
                    for (const dish of day.dishes || []) {
                        const existing = this.items.find(
                            (item) => item.menu_day_dish_id === dish.menu_day_dish_id && item.date === day.date,
                        );

                        if (existing) {
                            continue;
                        }

                        this.items.push({
                            menu_day_dish_id: dish.menu_day_dish_id,
                            name: dish.name,
                            price: dish.price,
                            quantity: 1,
                            date: day.date,
                        });
                    }
                }

                this.persistCart();
            },
        }));

        Alpine.data('ganeshaCheckoutSummary', (initialGroups, options) => ({
            groups: initialGroups,
            homeUrl: options?.homeUrl ?? '/',

            init() {
                this.syncFormField();
            },

            formatRub(kopecks) {
                return GaneshaCart.formatRub(kopecks);
            },

            formatDayLabel(isoDate) {
                const date = new Date(isoDate + 'T12:00:00');
                const weekday = new Intl.DateTimeFormat('ru-RU', { weekday: 'long' }).format(date);
                const dayMonth = new Intl.DateTimeFormat('ru-RU', {
                    day: 'numeric',
                    month: 'long',
                }).format(date);

                return weekday.charAt(0).toUpperCase() + weekday.slice(1) + ', ' + dayMonth;
            },

            dayCountLabel() {
                const count = this.groups.length;
                if (count === 1) {
                    return 'Самовывоз';
                }

                return count + ' ' + GaneshaCart.pluralRu(count, 'день', 'дня', 'дней') + ' самовывоза';
            },

            orderCountLabel() {
                const count = this.groups.length;

                return count + ' ' + GaneshaCart.pluralRu(count, 'заказ', 'заказа', 'заказов');
            },

            cartTotalKopecks() {
                return this.groups.reduce((sum, group) => {
                    return sum + (group.items || []).reduce(
                        (groupSum, item) => groupSum + item.price * item.quantity,
                        0,
                    );
                }, 0);
            },

            removeItem(pickupDate, menuDayDishId) {
                this.groups = this.groups
                    .map((group) => {
                        if (group.pickup_date !== pickupDate) {
                            return group;
                        }

                        return {
                            ...group,
                            items: (group.items || []).filter(
                                (item) => item.menu_day_dish_id !== menuDayDishId,
                            ),
                        };
                    })
                    .filter((group) => (group.items || []).length > 0);

                if (this.groups.length === 0) {
                    GaneshaCart.save([]);
                    window.location.href = this.homeUrl;

                    return;
                }

                GaneshaCart.save(GaneshaCart.itemsFromGroups(this.groups));
                this.syncFormField();
            },

            syncFormField() {
                const fieldId = options?.cartFieldId;
                if (!fieldId) {
                    return;
                }

                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = JSON.stringify(this.groups);
                }
            },
        }));
    }

    window.ganeshaCopy = async (text, button) => {
        try {
            await navigator.clipboard.writeText(text);
            if (button) {
                const original = button.textContent;
                button.textContent = 'Скопировано';
                setTimeout(() => {
                    button.textContent = original;
                }, 1600);
            }
        } catch {
            window.prompt('Скопируйте вручную:', text);
        }
    };

    window.ganeshaPollOrder = (url, onUpdate) => {
        const terminal = new Set(['paid', 'ready', 'completed', 'cancelled']);

        const tick = async () => {
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                onUpdate(data);
                if (terminal.has(data.status)) {
                    clearInterval(handle);
                }
            } catch {
                /* ignore transient network errors */
            }
        };

        tick();
        const handle = setInterval(tick, 5000);

        return () => clearInterval(handle);
    };

    window.ganeshaClearCartDate = (date) => {
        GaneshaCart.clearDate(date);
    };

    window.ganeshaClearCartDates = (dates) => {
        if (!Array.isArray(dates)) {
            return;
        }

        dates.forEach((date) => {
            if (typeof date === 'string' && date !== '') {
                GaneshaCart.clearDate(date);
            }
        });
    };
})();
