--
-- PostgreSQL database dump
--

\restrict rubZSsbGk08RQTR3tEOTnJPFgJI5978oks7hBo1hUL6uNg6Yval2ML8MdPvBBE9

-- Dumped from database version 15.16 (Debian 15.16-1.pgdg13+1)
-- Dumped by pg_dump version 15.16 (Debian 15.16-1.pgdg13+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: estates; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estates (
    id bigint NOT NULL,
    hash_id bigint NOT NULL,
    name text,
    price_czk integer,
    price_czk_m2 integer,
    usable_area integer,
    floor_number integer,
    city text,
    ward text,
    gps_lat numeric(10,6),
    gps_lon numeric(10,6),
    balcony boolean,
    cellar boolean,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    elevator boolean,
    building_condition text,
    construction_type text,
    energy_rating text,
    ownership text,
    loggia boolean,
    terrace boolean,
    parking boolean,
    garage boolean,
    description text,
    portal text DEFAULT 'sreality'::text NOT NULL,
    metro_distance integer,
    tram_distance integer,
    bus_distance integer,
    detail_url text,
    last_seen timestamp without time zone,
    active boolean DEFAULT true
);


ALTER TABLE public.estates OWNER TO realestate;

--
-- Name: estate_ai_reviews; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_ai_reviews (
    id integer NOT NULL,
    hash_id bigint,
    ai_score integer,
    verdict text,
    strengths text,
    weaknesses text,
    summary text,
    created_at timestamp without time zone DEFAULT now(),
    breakdown jsonb,
    model text
);


ALTER TABLE public.estate_ai_reviews OWNER TO realestate;

--
-- Name: estate_ai_reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_ai_reviews_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_ai_reviews_id_seq OWNER TO realestate;

--
-- Name: estate_ai_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_ai_reviews_id_seq OWNED BY public.estate_ai_reviews.id;


--
-- Name: estate_disposition_map; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_disposition_map (
    id integer NOT NULL,
    portal text NOT NULL,
    disposition text NOT NULL,
    category_sub_cb integer NOT NULL
);


ALTER TABLE public.estate_disposition_map OWNER TO realestate;

--
-- Name: estate_disposition_map_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_disposition_map_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_disposition_map_id_seq OWNER TO realestate;

--
-- Name: estate_disposition_map_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_disposition_map_id_seq OWNED BY public.estate_disposition_map.id;


--
-- Name: estate_price_history; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_price_history (
    id bigint NOT NULL,
    hash_id bigint,
    price_czk integer,
    captured_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.estate_price_history OWNER TO realestate;

--
-- Name: estate_price_history_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_price_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_price_history_id_seq OWNER TO realestate;

--
-- Name: estate_price_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_price_history_id_seq OWNED BY public.estate_price_history.id;


--
-- Name: estate_prices; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_prices (
    id integer NOT NULL,
    hash_id bigint,
    price_czk integer,
    recorded_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.estate_prices OWNER TO realestate;

--
-- Name: estate_prices_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_prices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_prices_id_seq OWNER TO realestate;

--
-- Name: estate_prices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_prices_id_seq OWNED BY public.estate_prices.id;


--
-- Name: estate_scoring_profiles; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_scoring_profiles (
    id bigint NOT NULL,
    name text NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    weight_price numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_price_m2 numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_metro numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_rooms numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_area numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_floor numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_condition numeric(6,2) DEFAULT 1.0 NOT NULL,
    weight_parking numeric(6,2) DEFAULT 1.0 NOT NULL
);


ALTER TABLE public.estate_scoring_profiles OWNER TO realestate;

--
-- Name: estate_scoring_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_scoring_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_scoring_profiles_id_seq OWNER TO realestate;

--
-- Name: estate_scoring_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_scoring_profiles_id_seq OWNED BY public.estate_scoring_profiles.id;


--
-- Name: estate_scoring_rules; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_scoring_rules (
    id integer NOT NULL,
    rule_group text NOT NULL,
    min_value integer,
    max_value integer,
    text_match text,
    points integer NOT NULL
);


ALTER TABLE public.estate_scoring_rules OWNER TO realestate;

--
-- Name: estate_scoring_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_scoring_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_scoring_rules_id_seq OWNER TO realestate;

--
-- Name: estate_scoring_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_scoring_rules_id_seq OWNED BY public.estate_scoring_rules.id;


--
-- Name: estate_search_profiles; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.estate_search_profiles (
    id bigint NOT NULL,
    name text NOT NULL,
    is_active boolean DEFAULT false,
    category_type_cb integer DEFAULT 1,
    category_main_cb integer DEFAULT 1,
    category_sub_cb text,
    locality_country_id integer DEFAULT 112,
    locality_region_id integer,
    building_condition text,
    price_to integer,
    ownership integer,
    floor_number_from integer,
    balcony boolean,
    cellar boolean,
    usable_area_from integer,
    limit_items integer DEFAULT 50,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    floor_number_to integer
);


ALTER TABLE public.estate_search_profiles OWNER TO realestate;

--
-- Name: estate_search_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estate_search_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estate_search_profiles_id_seq OWNER TO realestate;

--
-- Name: estate_search_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estate_search_profiles_id_seq OWNED BY public.estate_search_profiles.id;


--
-- Name: estates_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.estates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.estates_id_seq OWNER TO realestate;

--
-- Name: estates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.estates_id_seq OWNED BY public.estates.id;


--
-- Name: listings; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.listings (
    id integer NOT NULL,
    external_id text NOT NULL,
    source text NOT NULL,
    title text,
    price integer,
    area integer,
    url text,
    description text,
    first_seen timestamp without time zone DEFAULT now(),
    last_seen timestamp without time zone,
    last_price integer,
    hard_score integer,
    ai_score integer,
    final_score integer,
    parking boolean,
    balcony boolean,
    noise_risk text,
    risks text,
    raw_json jsonb
);


ALTER TABLE public.listings OWNER TO realestate;

--
-- Name: listings_id_seq; Type: SEQUENCE; Schema: public; Owner: realestate
--

CREATE SEQUENCE public.listings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.listings_id_seq OWNER TO realestate;

--
-- Name: listings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: realestate
--

ALTER SEQUENCE public.listings_id_seq OWNED BY public.listings.id;


--
-- Name: processed_data; Type: TABLE; Schema: public; Owner: realestate
--

CREATE TABLE public.processed_data (
    "workflowId" character varying(36) NOT NULL,
    context character varying(255) NOT NULL,
    "createdAt" timestamp(3) with time zone DEFAULT CURRENT_TIMESTAMP(3) NOT NULL,
    "updatedAt" timestamp(3) with time zone DEFAULT CURRENT_TIMESTAMP(3) NOT NULL,
    value text NOT NULL
);


ALTER TABLE public.processed_data OWNER TO realestate;

--
-- Name: v_estates_hard_score; Type: VIEW; Schema: public; Owner: realestate
--

CREATE VIEW public.v_estates_hard_score AS
 SELECT e.hash_id,
    e.portal,
    e.name,
    e.city,
    e.ward,
    e.price_czk,
    e.price_czk_m2,
    e.usable_area,
    e.floor_number,
    e.building_condition,
    e.construction_type,
    e.metro_distance,
    e.tram_distance,
    e.bus_distance,
    e.detail_url,
    e.last_seen,
    concat(
        CASE
            WHEN e.garage THEN 'G'::text
            WHEN e.parking THEN 'P'::text
            ELSE '-'::text
        END,
        CASE
            WHEN e.cellar THEN 'C'::text
            ELSE '-'::text
        END,
        CASE
            WHEN e.balcony THEN 'B'::text
            WHEN e.loggia THEN 'L'::text
            ELSE '-'::text
        END,
        CASE
            WHEN e.elevator THEN 'E'::text
            ELSE '-'::text
        END) AS features,
    (((((((((COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'price'::text) AND ((r.min_value IS NULL) OR (e.price_czk >= r.min_value)) AND ((r.max_value IS NULL) OR (e.price_czk <= r.max_value)))
         LIMIT 1), 0) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'price_m2'::text) AND ((r.min_value IS NULL) OR (e.price_czk_m2 >= r.min_value)) AND ((r.max_value IS NULL) OR (e.price_czk_m2 <= r.max_value)))
         LIMIT 1), 0)) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'area'::text) AND ((r.min_value IS NULL) OR (e.usable_area >= r.min_value)) AND ((r.max_value IS NULL) OR (e.usable_area <= r.max_value)))
         LIMIT 1), 0)) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((((e.elevator = true) AND (r.rule_group = 'floor_elevator'::text)) OR ((e.elevator = false) AND (r.rule_group = 'floor_no_elevator'::text))) AND ((r.min_value IS NULL) OR (e.floor_number >= r.min_value)) AND ((r.max_value IS NULL) OR (e.floor_number <= r.max_value)))
         LIMIT 1), 0)) +
        CASE
            WHEN (e.metro_distance IS NULL) THEN ( SELECT r.points
               FROM public.estate_scoring_rules r
              WHERE ((r.rule_group = 'metro'::text) AND (r.text_match = 'missing'::text))
             LIMIT 1)
            ELSE COALESCE(( SELECT r.points
               FROM public.estate_scoring_rules r
              WHERE ((r.rule_group = 'metro'::text) AND ((r.min_value IS NULL) OR (e.metro_distance >= r.min_value)) AND ((r.max_value IS NULL) OR (e.metro_distance <= r.max_value)))
             LIMIT 1), 0)
        END) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'tram'::text) AND (e.tram_distance IS NOT NULL) AND ((r.min_value IS NULL) OR (e.tram_distance >= r.min_value)) AND ((r.max_value IS NULL) OR (e.tram_distance <= r.max_value)))
         LIMIT 1), 0)) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'bus'::text) AND (e.bus_distance IS NOT NULL) AND ((r.min_value IS NULL) OR (e.bus_distance >= r.min_value)) AND ((r.max_value IS NULL) OR (e.bus_distance <= r.max_value)))
         LIMIT 1), 0)) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'condition'::text) AND (e.building_condition ~~* (('%'::text || r.text_match) || '%'::text)))
         LIMIT 1), 0)) + COALESCE(( SELECT r.points
           FROM public.estate_scoring_rules r
          WHERE ((r.rule_group = 'construction'::text) AND (e.construction_type ~~* (('%'::text || r.text_match) || '%'::text)))
         LIMIT 1), 0)) +
        CASE
            WHEN ((e.metro_distance <= 500) AND ((e.balcony = true) OR (e.loggia = true))) THEN 3
            ELSE 0
        END) AS hard_score
   FROM public.estates e
  WHERE (e.active = true);


ALTER TABLE public.v_estates_hard_score OWNER TO realestate;

--
-- Name: estate_ai_reviews id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_ai_reviews ALTER COLUMN id SET DEFAULT nextval('public.estate_ai_reviews_id_seq'::regclass);


--
-- Name: estate_disposition_map id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_disposition_map ALTER COLUMN id SET DEFAULT nextval('public.estate_disposition_map_id_seq'::regclass);


--
-- Name: estate_price_history id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_price_history ALTER COLUMN id SET DEFAULT nextval('public.estate_price_history_id_seq'::regclass);


--
-- Name: estate_prices id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_prices ALTER COLUMN id SET DEFAULT nextval('public.estate_prices_id_seq'::regclass);


--
-- Name: estate_scoring_profiles id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_scoring_profiles ALTER COLUMN id SET DEFAULT nextval('public.estate_scoring_profiles_id_seq'::regclass);


--
-- Name: estate_scoring_rules id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_scoring_rules ALTER COLUMN id SET DEFAULT nextval('public.estate_scoring_rules_id_seq'::regclass);


--
-- Name: estate_search_profiles id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_search_profiles ALTER COLUMN id SET DEFAULT nextval('public.estate_search_profiles_id_seq'::regclass);


--
-- Name: estates id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estates ALTER COLUMN id SET DEFAULT nextval('public.estates_id_seq'::regclass);


--
-- Name: listings id; Type: DEFAULT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.listings ALTER COLUMN id SET DEFAULT nextval('public.listings_id_seq'::regclass);


--
-- Name: processed_data PK_ca04b9d8dc72de268fe07a65773; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.processed_data
    ADD CONSTRAINT "PK_ca04b9d8dc72de268fe07a65773" PRIMARY KEY ("workflowId", context);


--
-- Name: estate_ai_reviews estate_ai_reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_ai_reviews
    ADD CONSTRAINT estate_ai_reviews_pkey PRIMARY KEY (id);


--
-- Name: estate_disposition_map estate_disposition_map_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_disposition_map
    ADD CONSTRAINT estate_disposition_map_pkey PRIMARY KEY (id);


--
-- Name: estate_disposition_map estate_disposition_map_portal_disposition_key; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_disposition_map
    ADD CONSTRAINT estate_disposition_map_portal_disposition_key UNIQUE (portal, disposition);


--
-- Name: estate_price_history estate_price_history_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_price_history
    ADD CONSTRAINT estate_price_history_pkey PRIMARY KEY (id);


--
-- Name: estate_prices estate_prices_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_prices
    ADD CONSTRAINT estate_prices_pkey PRIMARY KEY (id);


--
-- Name: estate_scoring_profiles estate_scoring_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_scoring_profiles
    ADD CONSTRAINT estate_scoring_profiles_pkey PRIMARY KEY (id);


--
-- Name: estate_scoring_rules estate_scoring_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_scoring_rules
    ADD CONSTRAINT estate_scoring_rules_pkey PRIMARY KEY (id);


--
-- Name: estate_search_profiles estate_search_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_search_profiles
    ADD CONSTRAINT estate_search_profiles_pkey PRIMARY KEY (id);


--
-- Name: estates estates_hash_id_key; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estates
    ADD CONSTRAINT estates_hash_id_key UNIQUE (hash_id);


--
-- Name: estates estates_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estates
    ADD CONSTRAINT estates_pkey PRIMARY KEY (id);


--
-- Name: listings listings_pkey; Type: CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.listings
    ADD CONSTRAINT listings_pkey PRIMARY KEY (id);


--
-- Name: estate_ai_reviews_hash_idx; Type: INDEX; Schema: public; Owner: realestate
--

CREATE UNIQUE INDEX estate_ai_reviews_hash_idx ON public.estate_ai_reviews USING btree (hash_id);


--
-- Name: idx_listing_last_seen; Type: INDEX; Schema: public; Owner: realestate
--

CREATE INDEX idx_listing_last_seen ON public.listings USING btree (last_seen);


--
-- Name: idx_listing_price; Type: INDEX; Schema: public; Owner: realestate
--

CREATE INDEX idx_listing_price ON public.listings USING btree (price);


--
-- Name: idx_listing_score; Type: INDEX; Schema: public; Owner: realestate
--

CREATE INDEX idx_listing_score ON public.listings USING btree (final_score);


--
-- Name: idx_listing_unique; Type: INDEX; Schema: public; Owner: realestate
--

CREATE UNIQUE INDEX idx_listing_unique ON public.listings USING btree (external_id, source);


--
-- Name: processed_data FK_06a69a7032c97a763c2c7599464; Type: FK CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.processed_data
    ADD CONSTRAINT "FK_06a69a7032c97a763c2c7599464" FOREIGN KEY ("workflowId") REFERENCES public.workflow_entity(id) ON DELETE CASCADE;


--
-- Name: estate_ai_reviews estate_ai_reviews_hash_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_ai_reviews
    ADD CONSTRAINT estate_ai_reviews_hash_id_fkey FOREIGN KEY (hash_id) REFERENCES public.estates(hash_id);


--
-- Name: estate_price_history estate_price_history_hash_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_price_history
    ADD CONSTRAINT estate_price_history_hash_id_fkey FOREIGN KEY (hash_id) REFERENCES public.estates(hash_id);


--
-- Name: estate_prices estate_prices_hash_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: realestate
--

ALTER TABLE ONLY public.estate_prices
    ADD CONSTRAINT estate_prices_hash_id_fkey FOREIGN KEY (hash_id) REFERENCES public.estates(hash_id);


--
-- PostgreSQL database dump complete
--

\unrestrict rubZSsbGk08RQTR3tEOTnJPFgJI5978oks7hBo1hUL6uNg6Yval2ML8MdPvBBE9

