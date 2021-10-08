// @flow

type InitType = {
    settings: Object,
    init?: boolean,
    commonVersion?: string,
    width: number,
    height: number,
};

const covers = class Covers {
    running: boolean;
    settings: Object;
    commonVersion: string;
    common: any;
    rootEle: any;
    saveEle: any;
    cancelEle: any;
    positionEle: any;
    cropY: number;
    dragStartY: number;
    imgWidth: number;
    imgHeight: number;
    paneWidth: number;
    paneHeight: number;
    recentPercent: number;
    changeMade: boolean;

    constructor({
        settings = {},
        init = false,
        commonVersion = "20210125",
        width,
        height,
    }: InitType) {
        this.running = false;
        this.settings = Object.assign(
            {
                coverSelector: ".cover-hasImage",
                activeClass: "cover--positioning",
                saveSelector: ".cover__save",
                cancelSelector: ".cover__cancel",
                positionSelector: ".cover__positionTrigger",
            },
            settings
        );

        this.commonVersion = commonVersion;
        this.common = window.themehouse.common[commonVersion];
        this.rootEle = null;
        this.saveEle = null;
        this.cancelEle = null;
        this.positionEle = null;
        this.cropY = 50;
        this.lastSaved = 50;
        this.dragStartY = -1;
        this.imgWidth = width;
        this.imgHeight = height;
        this.paneWidth = 0;
        this.paneHeight = 0;
        this.recentPercent = 50;
        this.changeMade = false;

        if (init) {
            this.init();
        }
    }

    register(): void {
        this.common.register({
            phase: "initGet",
            addon: "TH_Covers",
            func: this.initGet,
            order: 10,
        });
        this.common.register({
            phase: "initSet",
            addon: "TH_Covers",
            func: this.initSet,
            order: 10,
        });
    }

    init = () => {
        this.initGet();
        this.initSet();
    };

    initGet = () => {
        this.rootEle = window.document.querySelector(
            this.settings.coverSelector
        );
        this.saveEle = window.document.querySelector(
            this.settings.saveSelector
        );
        this.cancelEle = window.document.querySelector(
            this.settings.cancelSelector
        );
        this.positionEle = window.document.querySelector(
            this.settings.positionSelector
        );

        if (this.rootEle !== null) {
            this.cropY =
                parseFloat(this.rootEle.style.backgroundPositionY) || 50;
            this.lastSaved = this.cropY;
        }
    };

    initSet = () => {
        this.running = true;
        window.XF.Click.register("cover-position", this.positionCoverClick);
        if (window.location.search.indexOf("th_coversInit=1") >= 0) {
            const oldSearch = window.location.search;
            const splitSearch = oldSearch.replace("?", "").split("&");
            let newSearch = "";
            for (let i = 0, len = splitSearch.length; i < len; i++) {
                var currentSearch = splitSearch[i];
                if (currentSearch !== "th_coversInit=1") {
                    if (newSearch !== "") {
                        newSearch += "&";
                    }
                    newSearch += currentSearch;
                }
            }
            if (newSearch !== "") {
                newSearch = "?" + newSearch;
            }

            window.history.replaceState(
                {},
                "",
                window.location.href.replace(oldSearch, newSearch)
            );
            this.initDrag();
        }
    };

    enableSave = () => {
        if (this.changeMade === false) {
            this.saveEle.addEventListener("click", this.submit);
            this.saveEle.classList.remove("is-disabled");
            // this.changeMade = true;
        }
    };

    enableCancel = () => {
        this.cancelEle.addEventListener("click", this.cancel);
    };

    positionCoverClick = window.XF.Click.newHandler({
        eventNameSpace: "XFClickCover",
        init: () => {},
        click: () => this.initDrag(),
    });

    initDrag = () => {
        if (this.rootEle !== null) {
            this.enableSave();
            this.enableCancel();
            window.XF.MenuWatcher.closeAll();
            window.document
                .querySelector("html")
                .classList.add(this.settings.activeClass);
            this.rootEle.classList.add(this.settings.activeClass);
            this.rootEle.addEventListener("mousedown", this.dragStart);
            window.document.addEventListener("mousemove", this.drag);
            window.document.addEventListener("mouseup", this.dragEnd);
            if (window.XF.Feature.has("touchevents")) {
                this.rootEle.addEventListener("touchstart", this.dragStart, {
                    passive: false,
                });
                window.document.addEventListener("touchmove", this.drag, {
                    passive: false,
                });
                window.document.addEventListener("touchend", this.dragEnd, {
                    passive: false,
                });
                window.document.addEventListener("touchcancel", this.dragEnd, {
                    passive: false,
                });
            }
        }
        return false;
    };

    removeDrag = () => {
        window.document
            .querySelector("html")
            .classList.remove(this.settings.activeClass);
        this.rootEle.classList.remove(this.settings.activeClass);
        this.rootEle.removeEventListener("mousedown", this.dragStart);
        window.document.removeEventListener("mousemove", this.drag);
        window.document.removeEventListener("mouseup", this.dragEnd);
        if (window.XF.Feature.has("touchevents")) {
            this.rootEle.removeEventListener("touchstart", this.dragStart);
            window.document.removeEventListener("touchmove", this.drag);
            window.document.removeEventListener("touchend", this.dragEnd);
            window.document.removeEventListener("touchcancel", this.dragEnd);
        }
    };

    getYPos = (e: any) => {
        const root =
            window.XF.Feature.has("touchevents") && e.touches.length > 0
                ? e.touches[0]
                : e;

        return root.clientY || root.pageY || root.y;
    };

    dragStart = (e: any) => {
        this.paneWidth = this.rootEle.offsetWidth;
        this.paneHeight = this.rootEle.offsetHeight;
        this.dragStartY = this.getYPos(e);
    };

    drag = (e: any) => {
        const { dragStartY } = this;

        if (dragStartY > -1) {
            const currentY = this.getYPos(e);
            const dragDistance = dragStartY - currentY;
            const percent = this.findImgPercent(dragDistance);
            this.recentPercent = percent;

            this.setPosition(percent);

            if (window.XF.Feature.has("touchevents")) {
                e.preventDefault();
            }
        }
    };

    dragEnd = () => {
        if (this.dragStartY !== -1) {
            this.dragStartY = -1;
            this.cropY = this.recentPercent;
        }
    };

    findImgPercent = (dragDistance: number): number => {
        const { imgWidth, imgHeight, paneWidth, paneHeight, cropY } = this;

        const imgRatio = imgWidth / imgHeight;
        const paneRatio = paneWidth / paneHeight;
        if (imgRatio > paneRatio) {
            // return 50;
        }
        const currentHeight = imgHeight * (paneWidth / imgWidth);
        const percent = (dragDistance / currentHeight) * 100 + cropY;

        if (percent > 100) {
            return 100;
        }
        if (percent < 0) {
            return 0;
        }

        return percent;
    };

    setPosition = (val: number) => {
        this.rootEle.style.backgroundPositionY = `${val}%`;
    };

    submit = () => {
        this.removeDrag();
        window.XF.ajax("POST", this.settings.url, {
            cropY: this.cropY,
        });
        this.lastSaved = this.cropY;
    };

    cancel = () => {
        this.removeDrag();
        this.setPosition(this.lastSaved);
    };
};

if (typeof window.themehouse === "undefined") {
    window.themehouse = {};
}

window.themehouse.covers = {
    covers,
};

export default covers;
